<?php

namespace AppBundle\Tests\Controller;

use Doctrine\ORM\Tools\SchemaTool;
use Nelmio\Alice\Fixtures;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class WebHookControllerTest extends WebTestCase
{
    public static function dump($var)
    {
        fwrite(STDERR, print_r($var, true));
    }

    /**
     * {@inheritDoc}
     */
    protected function setUp()
    {
        self::bootKernel();
        // TODO Load fixtures
        $em = static::$kernel->getContainer()->get('doctrine.orm.default_entity_manager');
        $metaData = $em->getMetadataFactory()->getAllMetadata();
        if (!empty($metaData)) {
            $tool = new SchemaTool($em);
            $tool->dropSchema($metaData);
            $tool->createSchema($metaData);
            Fixtures::load(static::$kernel->getRootDir() . '/../src/AppBundle/DataFixtures/ORM/fixtures.yml', $em, ['providers' => [$this]]);
        }
    }

    public function testCompleteScenario()
    {
        // Create a new client to browse the application
        $client = static::createClient([], [
            'PHP_AUTH_USER' => 'admin',
            'PHP_AUTH_PW'   => 'admin',
        ]);

        // Create a new entry in the database
        $crawler = $client->request('GET', '/webhook/');
        $this->assertEquals(200, $client->getResponse()
                                        ->getStatusCode(), "Unexpected HTTP status code for GET /webhook/");
        $crawler = $client->click($crawler->selectLink('Create a new webhook')->link());

        // Fill in the form and submit it
        $form = $crawler->selectButton('Submit')->form([
            'web_hook[endpoint]' => 'webhook_test',
        ]);

        $client->submit($form);
        $crawler = $client->followRedirect();

        // Check data in the show view
        $this->assertGreaterThan(0, $crawler->filter('a:contains("webhook_test")')
                                            ->count(), 'Missing element a:contains("webhook_test")');
        // Delete the entity
        $client->submit($crawler->selectButton('Delete webhook_test')->form());
        $crawler = $client->followRedirect();

        // Check the entity has been delete on the list
        $this->assertNotRegExp('/webhook_test/', $client->getResponse()->getContent());
    }
}
