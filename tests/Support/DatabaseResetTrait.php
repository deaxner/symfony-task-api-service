<?php

namespace App\Tests\Support;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;

trait DatabaseResetTrait
{
    protected function resetDatabase(EntityManagerInterface $entityManager): void
    {
        $metadata = $entityManager->getMetadataFactory()->getAllMetadata();
        if ([] === $metadata) {
            return;
        }

        $schemaTool = new SchemaTool($entityManager);
        try {
            $schemaTool->dropSchema($metadata);
        } catch (\Throwable) {
        }
        $schemaTool->createSchema($metadata);
    }
}
