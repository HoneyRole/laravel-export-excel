<?php

namespace Maatwebsite\Excel;

use PhpOffice\PhpSpreadsheet\IOFactory;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\BeforeExport;
use Maatwebsite\Excel\Events\BeforeWriting;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use PhpOffice\PhpSpreadsheet\Writer\Csv;

class Writer
{
    use DelegatedMacroable, HasEventBus;

    /**
     * @var Spreadsheet
     */
    protected $spreadsheet;

    /**
     * @var object
     */
    protected $export;

    /**
     * @var string
     */
    protected $tmpPath;

    /**
     * @var string
     */
    protected $delimiter;

    /**
     * @var string
     */
    protected $enclosure;

    /**
     * @var string
     */
    protected $lineEnding;

    /**
     * @var bool
     */
    protected $useBom;

    /**
     * @var bool
     */
    protected $includeSeparatorLine;

    /**
     * @var bool
     */
    protected $excelCompatibility;

    /**
     * New Writer instance.
     */
    public function __construct()
    {
        $this->tmpPath              = config('excel.exports.temp_path', sys_get_temp_dir());
        $this->delimiter            = config('excel.exports.csv.delimiter', ',');
        $this->enclosure            = config('excel.exports.csv.enclosure', '"');
        $this->lineEnding           = config('excel.exports.csv.line_ending', PHP_EOL);
        $this->useBom               = config('excel.exports.csv.use_bom', false);
        $this->includeSeparatorLine = config('excel.exports.csv.include_separator_line', false);
        $this->excelCompatibility   = config('excel.exports.csv.excel_compatibility', false);
    }

    /**
     * @param object $export
     * @param string $writerType
     *
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     * @throws \PhpOffice\PhpSpreadsheet\Writer\Exception
     * @return string
     */
    public function export($export, string $writerType): string
    {
        if ($export instanceof WithEvents) {
            $this->registerListeners($export->registerEvents());
        }

        $this->spreadsheet = new Spreadsheet;
        $this->spreadsheet->disconnectWorksheets();

        $this->raise(new BeforeExport($this));

        if ($export instanceof WithTitle) {
            $this->spreadsheet->getProperties()->setTitle($export->title());
        }

        $sheetExports = [$export];
        if ($export instanceof WithMultipleSheets) {
            $sheetExports = $export->sheets();
        }

        foreach ($sheetExports as $sheetExportExport) {
            $this->addSheet($sheetExportExport);
        }

        $this->raise(new BeforeWriting($this));

        return $this->write($writerType);
    }

    /**
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     * @return Sheet
     */
    public function addNewSheet()
    {
        return new Sheet($this->spreadsheet->createSheet());
    }

    /**
     * @return Spreadsheet
     */
    public function getDelegate()
    {
        return $this->spreadsheet;
    }

    /**
     * @param object $sheetExport
     *
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     */
    protected function addSheet($sheetExport)
    {
        $this->addNewSheet()->export($sheetExport);
    }

    /**
     * @param string $writerType
     *
     * @throws \PhpOffice\PhpSpreadsheet\Writer\Exception
     * @return string: string
     */
    protected function write(string $writerType)
    {
        $fileName = $this->tempFile();

        $writer = IOFactory::createWriter($this->spreadsheet, $writerType);

        if ($writer instanceof Csv) {
            $writer->setDelimiter($this->delimiter);
            $writer->setEnclosure($this->enclosure);
            $writer->setLineEnding($this->lineEnding);
            $writer->setIncludeSeparatorLine($this->includeSeparatorLine);
            $writer->setExcelCompatibility($this->excelCompatibility);
        }

        $writer->save($fileName);

        return $fileName;
    }

    /**
     * @param string $delimiter
     *
     * @return Writer
     */
    public function setDelimiter(string $delimiter)
    {
        $this->delimiter = $delimiter;

        return $this;
    }

    /**
     * @param string $enclosure
     *
     * @return Writer
     */
    public function setEnclosure(string $enclosure)
    {
        $this->enclosure = $enclosure;

        return $this;
    }

    /**
     * @param string $lineEnding
     *
     * @return Writer
     */
    public function setLineEnding(string $lineEnding)
    {
        $this->lineEnding = $lineEnding;

        return $this;
    }

    /**
     * @param bool $includeSeparatorLine
     *
     * @return Writer
     */
    public function setIncludeSeparatorLine(bool $includeSeparatorLine)
    {
        $this->includeSeparatorLine = $includeSeparatorLine;

        return $this;
    }

    /**
     * @param bool $excelCompatibility
     *
     * @return Writer
     */
    public function setExcelCompatibility(bool $excelCompatibility)
    {
        $this->excelCompatibility = $excelCompatibility;

        return $this;
    }

    /**
     * @return string
     */
    protected function tempFile(): string
    {
        return tempnam($this->tmpPath, 'laravel-excel');
    }
}
