<?php

namespace AppBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class StartCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this
            ->setName('app:start')
            ->setDescription('Start the socket IO server');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);
        $em = $this->getContainer()->get('doctrine')->getManager();
        $socketIoConnector = $this->getContainer()->get('socket_io_connector')->ensureConnection();
        $webHooks = $em->getRepository('AppBundle:WebHook')->findAll();
        
        // TODO start socket.io server asynchronously
        
        // TODO create channel
        foreach ($webHooks as $webHook) {
            $socketIoConnector->createChannel($webHook);
            $io->comment('Channel created: ' . $webHook->getEndpoint());
        }
        $socketIoConnector->closeConnection();
    }
}