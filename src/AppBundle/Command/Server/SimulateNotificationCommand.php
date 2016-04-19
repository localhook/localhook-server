<?php

namespace AppBundle\Command\Server;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Process\Process;

class SimulateNotificationCommand extends ContainerAwareCommand
{
    /** @var SymfonyStyle */
    private $io;

    protected function configure()
    {
        $this
            ->setName('app:server:simulate-notification')
            ->setDescription('Simulate a notification to the socket IO server');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->io = new SymfonyStyle($input, $output);
        $socketIoConnector = $this->getContainer()->get('socket_io_connector')->ensureConnection();

        $em = $this->getContainer()->get('doctrine.orm.default_entity_manager');
        $webHook = $em->getRepository('AppBundle:WebHook')->findOneBy(['endpoint' => 'webhook_1']);
        $request = new Request();
        $request->query->add(['param_1' => 'value_1', 'param_2' => 'value_2']);
        $request->request->add(['param_1' => 'value_1', 'param_2' => 'value_2']);
        $request->setMethod('POST');
        $socketIoConnector->forwardNotification($webHook, $request);
        $output->writeln('Sent!');
    }
}