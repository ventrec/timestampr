<?php

namespace ventrec\Timestampr\Commands;

use Dotenv\Dotenv;
use Exception;
use PDO;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\FormatterHelper;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class UpdateTimestampColumnsCommand extends Command
{
    /**
     * @var FormatterHelper
     */
    private $formatter;
    /**
     * @var InputInterface
     */
    private $input;
    /**
     * @var OutputInterface
     */
    private $output;
    /**
     * @var PDO
     */
    private $dbConnection = null;
    /**
     * @var string
     */
    private $database;
    /**
     * @var int
     */
    private $port;
    /**
     * @var \PDOStatement
     */
    private $fetchColumnsQuery;
    /**
     * @var array
     */
    private $stats = ['tableCount' => 0, 'columnCount' => 0];

    protected function configure()
    {
        $this->addArgument('port', InputArgument::OPTIONAL, 'Port for database connection');

        $this->setName('update-timestamp')
             ->setDescription('Updates timestamp columns with invalid default values based on the mysql 5.6 changes.')
             ->setHelp('This command will update all timestamp columns that has an invalid default value ' .
                'in your database ');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->input = $input;
        $this->output = $output;
        $this->formatter = $this->getHelper('formatter');

        $this->validate();
        $this->setDbPort();
        $this->setDbConnection();
        $this->prepareQuery();

        $tables = $this->fetchTables();
        $this->stats['tableCount'] = count($tables);

        $progressBar = new ProgressBar($output, count($tables));
        $progressBar->start();

        foreach ($tables as $table) {
            $this->fetchColumnsQuery->execute([$this->database, $table]);

            $columns = array_map(function ($element) {
                return $element['COLUMN_NAME'];
            }, $this->fetchColumnsQuery->fetchAll(PDO::FETCH_ASSOC));

            $this->dbConnection->exec($this->buildUpdateQuery($table, $columns));
            $this->stats['columnCount'] += count($columns);
            $progressBar->advance();
        }

        $progressBar->finish();

        // Write a clean line in order to break the progress bar
        $this->output->writeln('');
        $this->output->writeln("Updated {$this->stats['columnCount']} columns in {$this->stats['tableCount']} tables.");

        // Close connection
        $this->dbConnection = null;
    }

    private function validate()
    {
        if (!file_exists('.env')) {
            $this->output->writeln(
                $this->formatter->formatSection('Error', 'No .env file found. Aborting...', 'error')
            );

            exit(1);
        }

        try {
            (new Dotenv(realpath('.')))->load();
        } catch (Exception $e) {
            $this->output->writeln(
                $this->formatter->formatSection('Warning', $e->getMessage(), 'error')
            );
        }

        $this->output->writeln('Found .env file');

        if ($this->isInvalidDbCredentials()) {
            $this->output->writeln(
                $this->formatter->formatSection('Error', 'Missing required database parameters', 'error')
            );

            exit(1);
        }
    }

    /**
     * Returns true if database credentials are invalid
     * @return bool
     */
    private function isInvalidDbCredentials()
    {
        return (empty(getenv('DB_HOST'))
            or empty(getenv('DB_USERNAME'))
            or empty(getenv('DB_PASSWORD'))
            or empty(getenv('DB_DATABASE')));
    }

    private function setDbPort()
    {
        if ($this->input->getArgument('port')) {
            $this->port = $this->input->getArgument('port');
        } else if (!empty(getenv('DB_PORT'))) {
            $this->port = getenv('DB_PORT');
        } else {
            $this->port = '3306';
        }
    }

    private function setDbConnection()
    {
        $host = $this->getDbHost();
        $username = getenv('DB_USERNAME');
        $password = getenv('DB_PASSWORD');
        $this->database = getenv('DB_DATABASE');

        try {
            $this->dbConnection = new PDO(
                "mysql:host=$host;port=$this->port;dbname=$this->database;charset=UTF8",
                $username,
                $password
            );

            $this->output->writeln('Connected to database.');
        } catch (Exception $e) {
            $this->output->writeln(
                $this->formatter->formatSection('Error', $e->getMessage(), 'error')
            );

            exit(1);
        }
    }

    private function getDbHost()
    {
        $host = getenv('DB_HOST');

        if ($host === 'localhost') {
            return '127.0.0.1';
        }

        return $host;
    }

    private function prepareQuery()
    {
        $this->fetchColumnsQuery = $this->dbConnection->prepare(
            "SELECT COLUMN_NAME FROM information_schema.columns WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? " .
            "AND DATA_TYPE = 'timestamp' AND IS_NULLABLE = 'NO' ORDER BY TABLE_NAME, ORDINAL_POSITION;"
        );
    }

    private function fetchTables()
    {
        $statement = $this->dbConnection->prepare(
            "SELECT DISTINCT TABLE_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND " .
            "DATA_TYPE = 'timestamp' AND IS_NULLABLE = 'NO' ORDER BY TABLE_NAME;"
        );

        $statement->execute([$this->database]);

        if ($statement->rowCount() === 0) {
            $this->output->writeln('No tables needs updating.');
            exit(1);
        }

        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        return array_map(function ($row) {
            return $row['TABLE_NAME'];
        }, $rows);
    }

    private function buildUpdateQuery($table, $columns)
    {
        $query = "ALTER TABLE {$table} ";

        foreach ($columns as $column) {
            $query .= "MODIFY COLUMN {$column} TIMESTAMP NULL, ";
        }

        return substr($query, 0, -2) . ";";
    }
}
