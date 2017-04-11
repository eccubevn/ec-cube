<?php

namespace DoctrineMigrations;

use Doctrine\DBAL\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;
use Eccube\Entity\Master\DeviceType;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20160630110710 extends AbstractMigration
{
    /**
     * @param Schema $schema
     */
    public function up(Schema $schema)
    {
        $app = \Eccube\Application::getInstance();

        $DeviceType = $app['eccube.repository.master.device_type']->find(DeviceType::DEVICE_TYPE_PC);

        $PageLayout = $app['eccube.repository.page_layout']->findOneBy(array(
            'url' => 'mypage_delivery_edit',
        ));
        if (!$PageLayout) {
            $PageLayout = new \Eccube\Entity\PageLayout();
            $PageLayout->setName('MYページ/お届け先編集')
                ->setDeviceType($DeviceType)
                ->setUrl('mypage_delivery_edit')
                ->setFileName('Mypage/delivery_edit')
                ->setEditFlg(2)
                ->setMetaRobots('noindex');
            $app['orm.em']->persist($PageLayout);
            $app['orm.em']->flush();
        }
    }

    /**
     * @param Schema $schema
     */
    public function down(Schema $schema)
    {

    }
}
