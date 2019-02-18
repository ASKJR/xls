<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use GuzzleHttp\Client;
use League\Csv\Writer;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xls;
use Illuminate\Support\Facades\Mail;
use App\Mail\SendUsersListReport;


class GenerateSendUserReport extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'report:users';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate and send users report';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $client = new Client();
        $request = $client->request('GET', 'https://randomuser.me/api/?inc=name,gender,email,dob&results=1000&nat=BR');
        $response = json_decode($request->getBody()->getContents(), true);
        $reportContent = $this->responseCsvFormatted($response["results"]);

        if ($this->generateCsvReport($reportContent)) {
            $today = $this->today();
            $this->generateXlsReport(storage_path('app/public/users-report/users.csv'), storage_path("app/public/users-report/users-$today.xls"));
            $this->generateXlsReport(storage_path('app/users.csv'), storage_path("app/users.xls"));
            Mail::to('niceuser@kindness.com')->send(new SendUsersListReport($this->getReportDownloadPath()));
        }

    }

    private function getFullName($user)
    {
        return ucwords($user["name"]["first"] . " " . $user["name"]["last"]);
    }

    private function getGender($user)
    {
        return $user["gender"] == 'female' ? "F" : "M";
    }

    private function getDob($user)
    {
        return (\Carbon\Carbon::parse($user["dob"]["date"]))->format('d/m/Y');
    }

    private function getReportHeader()
    {
        return ['Name', 'E-mail', 'DOB', 'Gender'];
    }

    private function responseCsvFormatted($users)
    {
        $csv = Writer::createFromFileObject(new \SplTempFileObject());
        $csv->setDelimiter(';');
        $csv->insertOne($this->getReportHeader());

        foreach ($users as $user) {
            $csv->insertOne([
                $this->getFullName($user),
                $user['email'],
                $this->getDob($user),
                $this->getGender($user)
            ]);
        }

        return $csv->__toString();
    }

    private function generateCsvReport($content)
    {
        try {
            $files = Storage::disk('public')->files('users-report');
            Storage::disk('public')->delete($files);
            Storage::disk('public')->put('users-report/users.csv', $content);
            Storage::put('users.csv', $content);
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    private function generateXlsReport($csvPath, $xlsPath)
    {
        $spreadsheet = new Spreadsheet();
        $reader = new \PhpOffice\PhpSpreadsheet\Reader\Csv();
        /* Set CSV parsing options */
        $reader->setDelimiter(';')
            ->setEnclosure('"')
            ->setSheetIndex(0);

        /* Load a CSV file and save as a XLS */
        $spreadsheet = $reader->load($csvPath);
        $writer = new Xls($spreadsheet);
        $spreadsheet->getDefaultStyle()
            ->getFont()
            ->setName('Arial')
            ->setSize(10);
        $spreadsheet->getProperties()
            ->setCreator("Alberto Kato")
            ->setTitle('users-report');

        $spreadsheet->getActiveSheet()
            ->setTitle('users-report');

        $writer->save($xlsPath);

        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);
    }

    private function getReportDownloadPath()
    {
        return asset('storage/users-report/users-' . $this->today() . '.xls');
    }

    private function today()
    {
        return date('Y-m-d');
    }
}
