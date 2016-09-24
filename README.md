# Timestampr

Timestampr is a tool that updates timestamp columns in your mysql database that is not nullable. The tool was made in order to update the database in a laravel project, hence the .env file requirement.

### Installation

```sh
composer install --global timestampr
```

### How to use it

Navigate to the root of your project folder where your .env file is located. The tool reads database parameters from the file (```DB_HOST```, ```DB_USERNAME```, ```DB_PASSWORD```, ```DB_DATABASE```) which is used to connect to the database. If needed, you can provide an optional parameter for the port.

Run the command.
```sh
timestampr
```

Run the tool with mysql port as a parameter.
```sh
timestampr 33060
```

License
----

MIT