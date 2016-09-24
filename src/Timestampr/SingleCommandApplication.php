<?php

namespace ventrec\Timestampr;

use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\InputInterface;
use ventrec\Timestampr\Commands\UpdateTimestampColumnsCommand;

class SingleCommandApplication extends Application
{
    protected function getCommandName(InputInterface $input)
    {
        return 'update-timestamp';
    }

    protected function getDefaultCommands()
    {
        $defaultCommands = parent::getDefaultCommands();

        $defaultCommands[] = new UpdateTimestampColumnsCommand();

        return $defaultCommands;
    }

    public function getDefinition()
    {
        $inputDefinition = parent::getDefinition();

        $inputDefinition->setArguments();

        return $inputDefinition;
    }
}
