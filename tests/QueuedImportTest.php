<?php

namespace Maatwebsite\Excel\Tests;

use Illuminate\Support\Facades\Queue;
use Maatwebsite\Excel\Jobs\ReadChunk;
use Illuminate\Queue\Events\JobProcessing;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Files\TemporaryFile;
use Illuminate\Foundation\Bus\PendingDispatch;
use Maatwebsite\Excel\Files\RemoteTemporaryFile;
use Maatwebsite\Excel\Tests\Data\Stubs\QueuedImport;
use Maatwebsite\Excel\Tests\Data\Stubs\AfterQueueImportJob;

class QueuedImportTest extends TestCase
{
    /**
     * Setup the test environment.
     */
    protected function setUp()
    {
        parent::setUp();

        $this->loadLaravelMigrations(['--database' => 'testing']);
        $this->loadMigrationsFrom(__DIR__ . '/Data/Stubs/Database/Migrations');
    }

    /**
     * @test
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage Importable should implement ShouldQueue to be queued.
     */
    public function cannot_queue_import_that_does_not_implement_should_queue()
    {
        $import = new class {
            use Importable;
        };

        $import->queue('import-batches.xlsx');
    }

    /**
     * @test
     */
    public function can_queue_an_import()
    {
        $import = new QueuedImport();

        $chain = $import->queue('import-batches.xlsx')->chain([
            new AfterQueueImportJob(5000),
        ]);

        $this->assertInstanceOf(PendingDispatch::class, $chain);
    }

    /**
     * @test
     */
    public function can_queue_import_with_remote_temp_disk()
    {
        config()->set('excel.temporary_files.remote_disk', 'test');

        // Delete the local temp file before each read chunk job
        // to simulate using a shared remote disk, without
        // having a dependency on a local temp file.
        Queue::before(function (JobProcessing $event) {
            if ($event->job->resolveName() === ReadChunk::class) {
                /** @var TemporaryFile $tempFile */
                $tempFile = $this->inspectJobProperty($event->job, 'temporaryFile');

                $this->assertInstanceOf(RemoteTemporaryFile::class, $tempFile);

                // Should exist remote
                $this->assertTrue(
                    $tempFile->exists()
                );

                $this->assertTrue(
                    unlink($tempFile->getLocalPath())
                );
            }
        });

        $import = new QueuedImport();

        $chain = $import->queue('import-batches.xlsx')->chain([
            new AfterQueueImportJob(5000),
        ]);

        $this->assertInstanceOf(PendingDispatch::class, $chain);
    }
}
