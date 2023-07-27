# SeedFromJSON

**Seed Your Database Using JSON files**

This package makes it easy to have your DB seeded from JSON files.
JSON files are expected to be named like the tables they contain the data for.
This library was created mainly because of how easy it is to create JSON files from within the PHPStorm IDE

It supports PHP 7+

- [Installation](#installation)
- [Configuration](#configuration)
- [Storage Setup](#storage-setup)
- [Usage](#usage)
- [Examples](#examples)
- [Quick Seeding](#quick-seeding)
- [Options](#options)


## Installation

Require this package with composer using the following command:

```bash
composer require bluekachina/seedfromjson
```

## Configuration

Publish the configuration for this package
```bash
php artisan vendor:publish --tag=config
```
|| ToDo: There's probably a more specific command you can use.  I think you would need to name this package specifically.


## Storage Setup

Create a disk in your `config/filessystems.php` file.
Disks probably already exist for `local`, `public`, `s3`, etc...
The name of the disk you create should match what is named within the config (default is `seed_content`)
```
    'seed_content' => [
        'driver' => 'local',
        'root' => database_path().'/seeders/seed_content',
    ],
```


## Usage
1) Create at least one JSON file within your `seed_content` folder
2) Make use of this library from within your Laravel project's `DatabaseSeeder.php` file.
3) Run `php artisan migrate --seed`

## Examples
This library is typically used in sandwich form.
1) Create a new instance of the class
```
$seedFromJson = new \bluekachina\seedfromjson\SeedFromJSON();
```

2) Queue up at least one model to be seeded 
```
$seedFromJson->addModelToSeedingQueue(User::class, OPT_TRUNCATE_TABLE | OPT_IMPORT_DATA | OPT_DISABLE_FK_CONSTRAINTS );
```

3) Begin seeding based on all the queued items that were just added
```
$seedFromJson->beginSeeding();
``` 
## Quick Seeding
Quick seeding can be enabled via config/env variable.
When provided with a truthy value, it will seed only a limited number of records.
The number of records is determined by another configuration variable, and defaults to `100`

## Options
Many different options have been made available.  These can be specified on a table-by-table basis
```
OPT_TRUNCATE_TABLE          => Determines whether or not to truncate the table connected to the model provided
OPT_IMPORT_DATA             => Determines if JSON file (filename matches tablename) is to be imported from
OPT_SKIP_PK                 => Determines if the PK field's value is to be preserved
OPT_DISABLE_FK_CONSTRAINTS  => Determines if foreign key constraints should be disabled prior to importing JSON data
OPT_NO_SEED_IN_PROD         => Determines if table is to be populated in a development environment only
OPT_NO_TRUNCATE_IN_PROD     => Determines if table is to be truncated in a development environment only
OPT_ALWAYS_FULL_SEED        => Determines if table is to be fully seeded -- even if running a quick seed
```



## License

The SeedFromJSON library is open-sourced software licensed under the [MIT license](http://opensource.org/licenses/MIT)

