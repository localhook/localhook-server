<?php

namespace AppBundle\Command\Server;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Process\Process;

class RunSocketIoCommand extends ContainerAwareCommand
{
    /** @var SymfonyStyle */
    private $io;

    protected function configure()
    {
        $this
            ->setName('app:server:run-socket-io')
            ->setDescription('Start the socket IO server');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->io = new SymfonyStyle($input, $output);
        $socketIoPort = $this->getContainer()->getParameter('socket_io_port');

        $process = new Process('SOCKET_IO_PORT=' . $socketIoPort . ' node src/AppBundle/Resources/SocketIo/server.js');
        $process->setTimeout(null)->start();

        $process->wait(function ($type, $buffer) {
            if (Process::ERR === $type) {
                echo 'ERR > ' . $buffer;
            } else {
                echo $buffer;
                if (strpos($buffer, 'listening on port')) {
                    $this->initChannels();
                    $this->io->comment('Channels successfully created');
                }
            }
        });
    }

    private function initChannels()
    {
        $this
            ->getContainer()
            ->get('socket_io_connector')
            ->ensureConnection()
            ->initChannels()
            ->closeConnection();
    }
}