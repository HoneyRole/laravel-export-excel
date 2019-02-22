<?php

namespace Maatwebsite\Excel;

use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Writer\Csv;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Maatwebsite\Excel\Concerns\WithCharts;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\BeforeExport;
use Illuminate\Contracts\Queue\ShouldQueue;
use Maatwebsite\Excel\Events\BeforeWriting;
use Maatwebsite\Excel\Helpers\FilePathHelper;
use Maatwebsite\Excel\Concerns\MapsCsvSettings;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithCustomCsvSettings;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithPreCalculateFormulas;

class Writer
{
    use DelegatedMacroable, HasEventBus, MapsCsvSettings;

    /**
     * @var Spreadsheet
     */
    protected $spreadsheet;

    /**
     * @var object
     */
    protected $exportable;

    /**
     * @var FilePathHelper
     */
    protected $filePathHelper;

    /**
     * @var string
     */
    protected $file;

    /**
     * New Writer instance.
     *
     * @param FilePathHelper $filePathHelper
     */
    public function __construct(FilePathHelper $filePathHelper)
    {
        $this->filePathHelper = $filePathHelper;

        $this->applyCsvSettings(config('excel.exports.csv', []));
        $this->setDefaultValueBinder();
    }

    /**
     * @param object $export
     * @param string $writerType
     *
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     * @return string
     */
    public function export($export, string $writerType): string
    {
        $this->open($export);

        $sheetExports = [$export];
        if ($export instanceof WithMultipleSheets) {
            $sheetExports = $export->sheets();
        }

        foreach ($sheetExports as $sheetExport) {
            $this->addNewSheet()->export($sheetExport);
        }

        return $this->write($export, $this->filePathHelper->generateTempFileName(), $writerType);
    }

    /**
     * @param object $export
     *
     * @return $this
     */
    public function open($export)
    {
        $this->exportable = $export;

        if ($export instanceof WithEvents) {
            $this->registerListeners($export->registerEvents());
        }

        $this->exportable  = $export;
        $this->spreadsheet = new Spreadsheet;
        $this->spreadsheet->disconnectWorksheets();

        if ($export instanceof WithCustomValueBinder) {
            Cell::setValueBinder($export);
        }

        $this->raise(new BeforeExport($this, $this->exportable));

        if ($export instanceof WithTitle) {
            $this->spreadsheet->getProperties()->setTitle($export->title());
        }

        return $this;
    }

    /**
     * @param string $tempFile
     * @param string $writerType
     *
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     * @throws \Maatwebsite\Excel\Exceptions\UnreadableFileException
     * @throws \PhpOffice\PhpSpreadsheet\Reader\Exception
     * @return Writer
     */
    public function reopen(string $tempFile, string $writerType)
    {
        $reader            = IOFactory::createReader($writerType);
        $this->spreadsheet = $reader->load($this->filePathHelper->getTempFile($tempFile));

        return $this;
    }

    /**
     * @param object $export
     * @param string $fileName
     * @param string $writerType
     *
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     * @throws \PhpOffice\PhpSpreadsheet\Writer\Exception
     * @return string
     */
    public function write($export, string $fileName, string $writerType)
    {
        $this->exportable = $export;
        $filePath         = $this->filePathHelper->getTempPath($fileName);

        $this->spreadsheet->setActiveSheetIndex(0);

        $this->raise(new BeforeWriting($this, $this->exportable));

        if ($export instanceof WithCustomCsvSettings) {
            $this->applyCsvSettings($export->getCsvSettings());
        }

        $writer = IOFactory::createWriter($this->spreadsheet, $writerType);

        if ($export instanceof WithCharts) {
            $writer->setIncludeCharts(true);
        }

        if ($writer instanceof Csv) {
            $writer->setDelimiter($this->delimiter);
            $writer->setEnclosure($this->enclosure);
            $writer->setLineEnding($this->lineEnding);
            $writer->setUseBOM($this->useBom);
            $writer->setIncludeSeparatorLine($this->includeSeparatorLine);
            $writer->setExcelCompatibility($this->excelCompatibility);
        }

        // Calculation settings
        $writer->setPreCalculateFormulas(
            $this->exportable instanceof WithPreCalculateFormulas
                ? true
                : config('excel.exports.pre_calculate_formulas', false)
        );

        $writer->save($filePath);

        if ($export instanceof ShouldQueue) {
            $this->filePathHelper->storeToTempDisk($fileName);
        }

        $this->spreadsheet->disconnectWorksheets();
        unset($this->spreadsheet);

        return $filePath;
    }

    /**
     * @param int|null $sheetIndex
     *
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     * @return Sheet
     */
    public function addNewSheet(int $sheetIndex = null)
    {
        return new Sheet($this->spreadsheet->createSheet($sheetIndex));
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
     * @return Spreadsheet
     */
    public function getDelegate()
    {
        return $this->spreadsheet;
    }

    /**
     * @return $this
     */
    public function setDefaultValueBinder()
    {
        Cell::setValueBinder(new DefaultValueBinder());

        return $this;
    }

    /**
     * @param int $sheetIndex
     *
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     * @return Sheet
     */
    public function getSheetByIndex(int $sheetIndex)
    {
        return new Sheet($this->getDelegate()->getSheet($sheetIndex));
    }

    /**
     * @param string $concern
     *
     * @return bool
     */
    public function hasConcern($concern): bool
    {
        return $this->exportable instanceof $concern;
    }
}
