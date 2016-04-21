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

    /** @var string */
    private $socketIoPort;

    protected function configure()
    {
        $this
            ->setName('app:server:run-socket-io')
            ->setDescription('Start the socket IO server');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->io = new SymfonyStyle($input, $output);
        $this->socketIoPort = $this->getContainer()->getParameter('socket_io_port');

        $this->killExistingSocketIoServer();
        $command = 'Execute: SOCKET_IO_PORT=' . $this->socketIoPort . ' node src/AppBundle/Resources/SocketIo/server.js';
        $this->io->comment($command);
        $process = new Process($command);
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

    private function killExistingSocketIoServer()
    {
        // Kill node if already started
        $command = 'lsof -n -i4TCP:' . $this->socketIoPort . ' | grep LISTEN';
        $checkNodeStarted = `$command`;
        if (strlen($checkNodeStarted) > 0) {
            $checkNodeStarted = preg_replace('/\s+/', ' ', $checkNodeStarted);
            $checkNodeStarted = explode(' ', $checkNodeStarted);
            $pid = $checkNodeStarted[1];
            `kill $pid`;
        }
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