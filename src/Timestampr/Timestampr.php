<?php

namespace ventrec\Timestampr;

class Timestampr
{
    private $name = 'Timestampr';
    private $version = '1.0';

    public function run()
    {
        $application = new SingleCommandApplication($this->name, $this->version);

        $application->run();
    }
}
