<?php

namespace AppBundle\Command;

use AppBundle\Entity\WebHook;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class CreateChannelCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this
            ->setName('app:create-channel')
            ->addArgument('endpoint', InputArgument::REQUIRED, 'The name of the endpoint.')
            ->addArgument('private_key', InputArgument::REQUIRED, 'The private key (40 chars).')
            ->setDescription('Start the socket IO server');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);
        $em = $this->getContainer()->get('doctrine')->getManager();
        $socketIoConnector = $this->getContainer()->get('socket_io_connector')->ensureConnection();

        $webHook = new WebHook();
        $webHook->setEndpoint($input->getArgument('endpoint'));
        $webHook->setPrivateKey($input->getArgument('private_key'));
        $webHook->setUsername('todo');// TODO

        $em->persist($webHook);
        $em->flush();

        $socketIoConnector->createChannel($webHook);

        $io->comment('Channel created: ' . $webHook->getId());
        $socketIoConnector->closeConnection();
    }
}