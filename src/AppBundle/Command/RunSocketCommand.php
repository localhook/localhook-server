<?php

namespace AppBundle\Command;

use AppBundle\Ratchet\Server;
use Ratchet\Http\HttpServer;
use Ratchet\Server\IoServer;
use Ratchet\WebSocket\WsServer;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Process\Process;

class RunSocketCommand extends ContainerAwareCommand
{
    /** @var SymfonyStyle */
    private $io;

    /** @var string */
    private $socketPort;

    protected function configure()
    {
        $this
            ->setName('app:run-socket')
            ->setDescription('Run the the socket server');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->io = new SymfonyStyle($input, $output);
        $this->socketPort = $this->getContainer()->getParameter('socket_port');

        $em = $this->getContainer()->get('doctrine.orm.default_entity_manager');
        $socketUrl = $this->getContainer()->getParameter('socket_url');
        $webUrl = $this->getContainer()->getParameter('web_server_url');
        $webHooks = $em->getRepository('AppBundle:WebHook')->findAll();
        $this->io->comment(count($webHooks) . ' webHook(s)');
        $server = new Server($em, $webHooks, $webUrl, $socketUrl);
        $server->setIo($this->io);

        $this->killExistingSocketServer();

        $ioServer = IoServer::factory(new HttpServer(new WsServer($server)), $this->socketPort);
        $this->io->comment('Run socket server on port ' . $this->socketPort . '...');
        $ioServer->run();
    }

    private function killExistingSocketServer()
    {
        // If socket already started
        $command = 'lsof -n -i4TCP:' . $this->socketPort . ' | grep LISTEN';
        $lsOfProcess = new Process($command);
        $lsOfProcess->run();
        $lsOfProcessOutput = $lsOfProcess->getIncrementalOutput();

        if (strlen($lsOfProcessOutput) > 0) {
            // Kill process
            $lsOfProcessOutput = preg_replace('/\s+/', ' ', $lsOfProcessOutput);
            $lsOfProcessOutput = explode(' ', $lsOfProcessOutput);
            $pid = $lsOfProcessOutput[1];

            $killProcess = new Process("kill $pid");
            $killProcess->mustRun();
        }
    }
}
