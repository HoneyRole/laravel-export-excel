<?php

namespace Maatwebsite\Excel\Tests\Concerns;

use Maatwebsite\Excel\Excel;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Tests\TestCase;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithCustomCsvSettings;

class WithCustomCsvSettingsTest extends TestCase
{
    /**
     * @var Excel
     */
    protected $SUT;

    public function setUp()
    {
        parent::setUp();

        $this->SUT = $this->app->make(Excel::class);
    }

    /**
     * @test
     */
    public function can_store_csv_export_with_custom_settings()
    {
        $export = new class implements FromCollection, WithCustomCsvSettings
        {
            /**
             * @return Collection
             */
            public function collection()
            {
                return collect([
                    ['A1', 'B1'],
                    ['A2', 'B2'],
                ]);
            }

            /**
             * @return array
             */
            public function getCsvSettings(): array
            {
                return [
                    'delimiter'              => ';',
                    'enclosure'              => '',
                    'line_ending'            => PHP_EOL,
                    'use_bom'                => true,
                    'include_separator_line' => true,
                    'excel_compatibility'    => false,
                ];
            }
        };

        $this->SUT->store($export, 'custom-csv.csv');

        $contents = file_get_contents(__DIR__ . '/../Data/Disks/Local/custom-csv.csv');

        $this->assertContains('sep=;', $contents);
        $this->assertContains('A1;B1', $contents);
        $this->assertContains('A2;B2', $contents);
    }

    /**
     * @test
     */
    public function can_read_csv_import_with_custom_settings()
    {
        $import = new class implements WithCustomCsvSettings
        {
            /**
             * @return array
             */
            public function getCsvSettings(): array
            {
                return [
                    'delimiter'        => ';',
                    'enclosure'        => '"',
                    'escape_character' => '\\',
                    'contiguous'       => true,
                    'input_encoding'   => 'UTF-8',
                ];
            }
        };

        $array = $this->SUT->import($import, 'csv-with-other-delimiter.csv')->toArray();

        $this->assertEquals([
            [
                ['A1', 'B1'],
                ['A2', 'B2'],
            ],
        ], $array);
    }

    /**
     * @test
     */
    public function cannot_read_with_wrong_delimiter()
    {
        $import = new class implements WithCustomCsvSettings
        {
            /**
             * @return array
             */
            public function getCsvSettings(): array
            {
                return [
                    'delimiter' => ',',
                ];
            }
        };

        $array = $this->SUT->import($import, 'csv-with-other-delimiter.csv')->toArray();

        $this->assertEquals([
            [
                ['A1;B1'],
                ['A2;B2'],
            ],
        ], $array);
    }
}
