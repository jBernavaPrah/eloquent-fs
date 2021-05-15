# Eloquent FileSystem

... Inspired by Mongo GridFS

### Attention: it's not production ready!

The EloquentFS component implement the PHP Stream Wrapper using Eloquent Models as storage.

## Install:

... package here..

## Usage:

```php
use JBernavaPrah\EloquentFS\Models\File;

// You can use database transaction..
//DB::beginTransactions();

$file = new File(); // It's a Eloquent model...
$file->id = '/path_or_string'; //generated random dynamically otherwise 

// ... with some sugar on top...
$stream = $file->open('r'); // allowed mode are: r, r+, w, w+, a, a+

// open new stream in append mode (a+)
$file->write("Some data"); // 9
$file->flush(); // to flush all data and mantain opened stream or.. 
$file->close(); // to flush all data and close it..


// will be reopened in append mode 
$file->read(9); // "Some data"

$file->tell(); // 9
$file->seek(5); // 0 or -1 (see fseek())
$file->read(4); // "data"



$file->delete(); // soft Delete
$file->forceDelete(); // Delete


```