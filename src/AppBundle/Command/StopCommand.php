<?php

namespace AppBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class StopCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this
            ->setName('app:stop')
            ->setDescription('Stop the socket IO server')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        // TODO stop socket.io server
    }
}