<?php

namespace Maatwebsite\Excel\Tests\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\OnEachRow;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithBatchInserts;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Row;
use Maatwebsite\Excel\Tests\Data\Stubs\Database\User;
use Maatwebsite\Excel\Tests\TestCase;
use Maatwebsite\Excel\Validators\ValidationException;

class WithValidationTest extends TestCase
{
    /**
     * Setup the test environment.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->loadLaravelMigrations(['--database' => 'testing']);
        $this->loadMigrationsFrom(dirname(__DIR__) . '/Data/Stubs/Database/Migrations');
    }

    /**
     * @test
     */
    public function can_validate_rows()
    {
        $import = new class implements ToModel, WithValidation {
            use Importable;

            /**
             * @param array $row
             *
             * @return Model|null
             */
            public function model(array $row)
            {
                return new User([
                    'name'     => $row[0],
                    'email'    => $row[1],
                    'password' => 'secret',
                ]);
            }

            /**
             * @return array
             */
            public function rules(): array
            {
                return [
                    '1' => Rule::in(['patrick@maatwebsite.nl']),
                ];
            }
        };

        try {
            $import->import('import-users.xlsx');
        } catch (ValidationException $e) {
            $this->validateFailure($e, 2, '1', [
                'The selected 1 is invalid.',
            ]);

            $this->assertEquals([
                [
                    'There was an error on row 2. The selected 1 is invalid.',
                ],
            ], $e->errors());
        }

        $this->assertInstanceOf(ValidationException::class, $e ?? null);
    }

    /**
     * @test
     */
    public function can_validate_rows_with_closure_validation_rules()
    {
        $import = new class implements ToModel, WithValidation {
            use Importable;

            /**
             * @param array $row
             *
             * @return Model|null
             */
            public function model(array $row)
            {
                return new User([
                    'name'     => $row[0],
                    'email'    => $row[1],
                    'password' => 'secret',
                ]);
            }

            /**
             * @return array
             */
            public function rules(): array
            {
                return [
                    '1' => function ($attribute, $value, $onFail) {
                        if ($value !== 'patrick@maatwebsite.nl') {
                            $onFail(sprintf('Value in column 1 is not an allowed e-mail.'));
                        }
                    },
                ];
            }
        };

        try {
            $import->import('import-users.xlsx');
        } catch (ValidationException $e) {
            $this->validateFailure($e, 2, '1', [
                'Value in column 1 is not an allowed e-mail.',
            ]);

            $this->assertEquals([
                [
                    'There was an error on row 2. Value in column 1 is not an allowed e-mail.',
                ],
            ], $e->errors());
        }

        $this->assertInstanceOf(ValidationException::class, $e ?? null);
    }

    /**
     * @test
     */
    public function can_validate_rows_with_custom_validation_rule_objects()
    {
        $import = new class implements ToModel, WithValidation {
            use Importable;

            /**
             * @param array $row
             *
             * @return Model|null
             */
            public function model(array $row)
            {
                return new User([
                    'name'     => $row[0],
                    'email'    => $row[1],
                    'password' => 'secret',
                ]);
            }

            /**
             * @return array
             */
            public function rules(): array
            {
                return [
                    '1' => new class implements \Illuminate\Contracts\Validation\Rule {
                        /**
                         * @param  string $attribute
                         * @param  mixed  $value
                         *
                         * @return bool
                         */
                        public function passes($attribute, $value)
                        {
                            return $value === 'patrick@maatwebsite.nl';
                        }

                        /**
                         * Get the validation error message.
                         *
                         * @return string|array
                         */
                        public function message()
                        {
                            return 'Value is not an allowed e-mail.';
                        }
                    },
                ];
            }
        };

        try {
            $import->import('import-users.xlsx');
        } catch (ValidationException $e) {
            $this->validateFailure($e, 2, '1', [
                'Value is not an allowed e-mail.',
            ]);

            $this->assertEquals([
                [
                    'There was an error on row 2. Value is not an allowed e-mail.',
                ],
            ], $e->errors());
        }

        $this->assertInstanceOf(ValidationException::class, $e ?? null);
    }

    /**
     * @test
     */
    public function can_validate_rows_with_conditionality()
    {
        $import = new class implements ToModel, WithValidation {
            use Importable;

            /**
             * @param array $row
             *
             * @return Model|null
             */
            public function model(array $row)
            {
                return new User([
                    'name'     => $row[0],
                    'email'    => $row[1],
                    'password' => 'secret',
                ]);
            }

            /**
             * @return array
             */
            public function rules(): array
            {
                return [
                    'conditional_required_column' => 'required_if:1,patrick@maatwebsite.nl',
                ];
            }
        };

        try {
            $import->import('import-users.xlsx');
        } catch (ValidationException $e) {
            $this->validateFailure($e, 1, 'conditional_required_column', [
                'The conditional_required_column field is required when 1.1 is patrick@maatwebsite.nl.',
            ]);
        }

        $this->assertInstanceOf(ValidationException::class, $e ?? null);
    }

    /**
     * @test
     */
    public function can_validate_with_custom_attributes()
    {
        $import = new class implements ToModel, WithValidation {
            use Importable;

            /**
             * @param array $row
             *
             * @return Model|null
             */
            public function model(array $row)
            {
                return new User([
                    'name'     => $row[0],
                    'email'    => $row[1],
                    'password' => 'secret',
                ]);
            }

            /**
             * @return array
             */
            public function rules(): array
            {
                return [
                    '1' => Rule::in(['patrick@maatwebsite.nl']),
                ];
            }

            /**
             * @return array
             */
            public function customValidationAttributes()
            {
                return ['1' => 'email'];
            }
        };

        try {
            $import->import('import-users.xlsx');
        } catch (ValidationException $e) {
            $this->validateFailure($e, 2, 'email', [
                'The selected email is invalid.',
            ]);
        }

        $this->assertInstanceOf(ValidationException::class, $e ?? null);
    }

    /**
     * @test
     */
    public function can_validate_with_custom_message()
    {
        $import = new class implements ToModel, WithValidation {
            use Importable;

            /**
             * @param array $row
             *
             * @return Model|null
             */
            public function model(array $row)
            {
                return new User([
                    'name'     => $row[0],
                    'email'    => $row[1],
                    'password' => 'secret',
                ]);
            }

            /**
             * @return array
             */
            public function rules(): array
            {
                return [
                    '1' => Rule::in(['patrick@maatwebsite.nl']),
                ];
            }

            /**
             * @return array
             */
            public function customValidationMessages()
            {
                return [
                    '1.in' => 'Custom message for :attribute.',
                ];
            }
        };

        try {
            $import->import('import-users.xlsx');
        } catch (ValidationException $e) {
            $this->validateFailure($e, 2, '1', [
                'Custom message for 1.',
            ]);
        }

        $this->assertInstanceOf(ValidationException::class, $e ?? null);
    }

    /**
     * @test
     */
    public function can_validate_rows_with_headings()
    {
        $import = new class implements ToModel, WithHeadingRow, WithValidation {
            use Importable;

            /**
             * @param array $row
             *
             * @return Model|null
             */
            public function model(array $row)
            {
                return new User([
                    'name'     => $row['name'],
                    'email'    => $row['email'],
                    'password' => 'secret',
                ]);
            }

            /**
             * @return array
             */
            public function rules(): array
            {
                return [
                    'email' => Rule::in(['patrick@maatwebsite.nl']),
                ];
            }
        };

        try {
            $import->import('import-users-with-headings.xlsx');
        } catch (ValidationException $e) {
            $this->validateFailure($e, 3, 'email', [
                'The selected email is invalid.',
            ]);
        }

        $this->assertInstanceOf(ValidationException::class, $e ?? null);
    }

    /**
     * @test
     */
    public function can_validate_rows_in_batches()
    {
        $import = new class implements ToModel, WithHeadingRow, WithBatchInserts, WithValidation {
            use Importable;

            /**
             * @param array $row
             *
             * @return Model|null
             */
            public function model(array $row)
            {
                return new User([
                    'name'     => $row['name'],
                    'email'    => $row['email'],
                    'password' => 'secret',
                ]);
            }

            /**
             * @return int
             */
            public function batchSize(): int
            {
                return 2;
            }

            /**
             * @return array
             */
            public function rules(): array
            {
                return [
                    'email' => Rule::in(['patrick@maatwebsite.nl']),
                ];
            }
        };

        try {
            $import->import('import-users-with-headings.xlsx');
        } catch (ValidationException $e) {
            $this->validateFailure($e, 3, 'email', [
                'The selected email is invalid.',
            ]);
        }

        $this->assertInstanceOf(ValidationException::class, $e ?? null);
    }

    /**
     * @test
     */
    public function can_validate_using_oneachrow()
    {
        $import = new class implements OnEachRow, WithHeadingRow, WithValidation {
            use Importable;

            /**
             * @param Row $row
             *
             * @return Model|null
             */
            public function onRow(Row $row)
            {
                $values = $row->toArray();

                return new User([
                    'name'     => $values['name'],
                    'email'    => $values['email'],
                    'password' => 'secret',
                ]);
            }

            /**
             * @return array
             */
            public function rules(): array
            {
                return [
                    'email' => Rule::in(['patrick@maatwebsite.nl']),
                ];
            }
        };

        try {
            $import->import('import-users-with-headings.xlsx');
        } catch (ValidationException $e) {
            $this->validateFailure($e, 3, 'email', [
                'The selected email is invalid.',
            ]);
        }

        $this->assertInstanceOf(ValidationException::class, $e ?? null);
    }

    /**
     * @param ValidationException $e
     * @param int                 $row
     * @param string              $attribute
     * @param array               $messages
     */
    private function validateFailure(ValidationException $e, int $row, string $attribute, array $messages)
    {
        $failures = $e->failures();
        $failure  = head($failures);

        $this->assertEquals($row, $failure->row());
        $this->assertEquals($attribute, $failure->attribute());
        $this->assertEquals($messages, $failure->errors());
    }
}
