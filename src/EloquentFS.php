<?php


namespace JBernavaPrah\EloquentFS;


use Illuminate\Container\Container;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Database\Capsule\Manager;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Database\Migrations\DatabaseMigrationRepository;
use Illuminate\Database\Migrations\MigrationRepositoryInterface;
use Illuminate\Database\Migrations\Migrator;
use JBernavaPrah\EloquentFS\Models\FsFile;
use JBernavaPrah\EloquentFS\Models\FsFileChunk;

class EloquentFS
{

    /**
     * Default chunk to save in database.
     * @var int
     */
    public static $defaultChunkSize = 261120;

    /**
     * Run migrations
     * @var bool
     */
    public static bool $runMigrations = True;

    /**
     * Change the connection used for Migrations and FsFile/FsFileChunk Models
     * @var string|null
     */
    public static ?string $connection = null;

    /**
     * File Model to use to save on database.
     * @var string
     */
    public static string $defaultFileClass = FsFile::class;

    /**
     * Chunk File Model to use to save on database.
     * @var string
     */
    public static string $defaultChunkFileClass = FsFileChunk::class;


    /**
     * @param Manager $manager
     * @param string $connection
     * @param array $paths
     * @throws BindingResolutionException
     * @deprecated
     */
    public static function migrate(Manager $manager, string $connection = 'default', array $paths = [__DIR__ . '/../database/migrations'])
    {

        $currentConnection = $manager->getDatabaseManager()->getDefaultConnection();
        $manager->getDatabaseManager()->setDefaultConnection($connection);

        $container = Container::getInstance();
        $databaseMigrationRepository = new DatabaseMigrationRepository($manager->getDatabaseManager(), 'migrations');
        if (!$databaseMigrationRepository->repositoryExists()) {
            $databaseMigrationRepository->createRepository();
        }

        $container->instance(MigrationRepositoryInterface::class, $databaseMigrationRepository);
        $container->instance(ConnectionResolverInterface::class, $manager->getDatabaseManager());

        /** @var Migrator $migrator */
        $migrator = $container->make(Migrator::class);
        $migrator->run($paths);

        $manager->getDatabaseManager()->setDefaultConnection($currentConnection);

    }

}