# Eloquent FileSystem

... Inspired by Mongo GridFS

### Attention: it's not production ready!

The EloquentFS component implement the PHP Stream Wrapper using Eloquent Models as storage.

## Install:

```bash
composer install jbernavaprah/eloquent-fs
```
 
## Prerequisite:

### With laravel:

Nothing special to do, the service provider will be already loaded and with him also the migrations. Simply run:

```bash
php artisan migrate
```

### Standalone:

You need to have the database manager configured.

```php
// configure your DB manager
use Illuminate\Database\Capsule\Manager;
use JBernavaPrah\EloquentFS\EloquentFSStreamWrapper;

$db = new Manager();
$db->addConnection( [
        'driver' => 'sqlite',
        'database' => ':memory:',
        'prefix' => '',
    ]);
$db->setAsGlobal();
$db->bootEloquent();

// Call this command only .
// Will create the migrations table and call all the migrations on database/migrations directory.

EloquentFSStreamWrapper::migrate($db, $connection = 'default');

```

For the standalone execution, you need also register the wrapper:

```php
use JBernavaPrah\EloquentFS\EloquentFSStreamWrapper;
// register the wrapper..
EloquentFSStreamWrapper::register();
```

Note: Used with laravel, this is done automatically by service provider.

## Basic usage:

After registering the wrapper, you can use directly with php functions. To use correctly the wrapper, you need to prefix
the path with `efs://`. This will tell to php to use the correct wrapper.

The path will be tratted as `id` of the file on database.

```php
touch('efs://file.txt');
file_put_contents('efs://file.txt', "foobar\n");
echo file_get_contents('efs://file.txt'); // "foobar\n"
copy('efs://file.txt', 'efs://other_file.txt');
unlink('efs://file.txt');
unlink('efs://other_file.txt');

```

### Use with Eloquent model
You can also use directly the Eloquent Model shipped with EloquentFS, or you can extend it to add additional
functionality to the model.

```php
use JBernavaPrah\EloquentFS\Models\File;

$file = new File(); // It's a Eloquent model...
$file->id = 'file.txt'; //generated random dynamically otherwise 

// open new stream in append mode (a+)
$file->write("Some data"); // 9
// will be reopened in append mode 
$file->read(9); // "Some data"

$file->delete(); // Delete


```


## How to help:
Clone this repository, create new branch, do all your changes and then push back. 
I will be glad to merge its! 

## Missing implementations:
1. The locking file with `flock()`.
2. Need a performance review. A comparison may by with MongoDB will be super! 
3. It's not production ready.
4. Need a testing review.
