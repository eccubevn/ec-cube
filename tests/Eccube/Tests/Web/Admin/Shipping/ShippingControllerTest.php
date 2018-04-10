<?php

namespace Eccube\Tests\Web\Admin\Shipping;

use Eccube\Entity\Master\ShippingStatus;
use Eccube\Entity\Shipping;
use Eccube\Repository\DeliveryRepository;
use Eccube\Repository\Master\PrefRepository;
use Eccube\Repository\ShippingRepository;
use Eccube\Tests\Web\Admin\AbstractAdminWebTestCase;

class ShippingControllerTest extends AbstractAdminWebTestCase
{

    /**
     * @var ShippingRepository
     */
    protected $shippingRepository;


    public function setUp()
    {
        parent::setUp();

        $this->shippingRepository = $this->container->get(ShippingRepository::class);
        $Pref = $this->container->get(PrefRepository::class)->find(1);
        $Delivery = $this->container->get(DeliveryRepository::class)->find(1);

        // FIXME: Should remove exist data before generate data for test
        $this->deleteAllRows(array('dtb_shipping'));
        for ($i = 0; $i < 10; $i++) {
            $shipping = new Shipping();
            $shipping->setName01('Name');
            $shipping->setName02('Test');
            $shipping->setKana01('セ');
            $shipping->setKana02('イ');
            $shipping->setZip01('111');
            $shipping->setZip02('2222');
            $shipping->setPref($Pref);
            $shipping->setAddr01('1111');
            $shipping->setAddr02('2222');
            $shipping->setTel01('1111');
            $shipping->setTel02('2222');
            $shipping->setTel03('3333');
            $shipping->setDelivery($Delivery);
            $this->entityManager->persist($shipping);
            $this->entityManager->flush();
        }
    }

    public function testIndex()
    {
        $this->client->request(
            'GET',
            $this->generateUrl('admin_shipping')
        );
        $this->assertTrue($this->client->getResponse()->isSuccessful());
    }


    public function testIndexInitial()
    {
        // 初期表示時検索条件テスト
        $crawler = $this->client->request(
            'GET',
            $this->generateUrl('admin_shipping')
        );
        $this->assertTrue($this->client->getResponse()->isSuccessful());

        $this->expected = '検索結果 : 10 件が該当しました';
        $this->actual = $crawler->filter('#search_form .c-outsideBlock__contents.mb-3 span')->text();
        $this->verify();
    }


    public function testSearchOrderByName()
    {
        /** @var Shipping $Shipping */
        $Shipping = $this->shippingRepository->findOneBy(array());
        $name = $Shipping->getName01();
        $Shippings = $this->shippingRepository->findBy(array('name01' => $name));
        $cnt = count($Shippings);

        $crawler = $this->client->request(
            'POST', $this->generateUrl('admin_shipping'), array(
                'admin_search_shipping' => array(
                    '_token' => 'dummy',
                    'multi' => $name,
                )
            )
        );
        $this->assertTrue($this->client->getResponse()->isSuccessful());

        $this->expected = '検索結果 : ' . $cnt . ' 件が該当しました';
        $this->actual = $crawler->filter('#search_form .c-outsideBlock__contents.mb-3 span')->text();
        $this->verify();

        $crawler = $this->client->request(
            'POST', $this->generateUrl('admin_shipping'), array(
                'admin_search_shipping' => array(
                    '_token' => 'dummy',
                    'name' => $name,
                )
            )
        );
        $this->assertTrue($this->client->getResponse()->isSuccessful());

        $this->expected = '検索結果 : '.$cnt.' 件が該当しました';
        $this->actual = $crawler->filter('#search_form .c-outsideBlock__contents.mb-3 span')->text();
        $this->verify();
    }

    public function testIndexWithNext()
    {
        $crawler = $this->client->request(
            'POST',
            $this->generateUrl('admin_shipping').'?page_count=30',
            array(
                'admin_search_shipping' => array(
                    '_token' => 'dummy',
                )
            )
        );

        // 次のページへ遷移
        $crawler = $this->client->request(
            'GET',
            $this->generateUrl('admin_shipping_page', array('page_no' => 2))
        );
        $this->assertTrue($this->client->getResponse()->isSuccessful());

        $this->expected = '検索結果 : 10 件が該当しました';
        $this->actual = $crawler->filter('#search_form .c-outsideBlock__contents.mb-3 span')->text();
        $this->verify();
    }


    public function testExportShipping()
    {
        $this->markTestIncomplete('Still not implement export csv.');
        // 10件ヒットするはずの検索条件
        $crawler = $this->client->request(
            'POST',
            $this->generateUrl('admin_shipping'),
            array(
                'admin_search_shipping' => array(
                    '_token' => 'dummy',
                )
            )
        );
        $this->assertTrue($this->client->getResponse()->isSuccessful());
        $this->expected = '検索結果 : 10 件が該当しました';
        $this->actual = $crawler->filter('#search_form .c-outsideBlock__contents.mb-3 span')->text();
        $this->verify();

        // TODO: Implement export csv
    }

    public function testMarkAsShipped()
    {
        $this->client->enableProfiler();

        $Order = $this->createOrder($this->createCustomer());
        /** @var Shipping $Shipping */
        $Shipping = $Order->getShippings()->first();
        $Shipping->setShippingStatus($this->entityManager->find(ShippingStatus::class, ShippingStatus::PREPARED));
        $this->entityManager->persist($Shipping);
        $this->entityManager->flush();

        $this->client->request(
            'PUT',
            $this->generateUrl('admin_shipping_mark_as_shipped', ['id' => $Shipping->getId()])
        );

        $this->assertTrue($this->client->getResponse()->isSuccessful());

        $Messages = $this->getMailCollector(false)->getMessages();
        self::assertEquals(0, count($Messages));
    }

    public function testMarkAsShipped_sendNotifyMail()
    {
        $this->client->enableProfiler();

        $Order = $this->createOrder($this->createCustomer());
        /** @var Shipping $Shipping */
        $Shipping = $Order->getShippings()->first();
        $Shipping->setShippingStatus($this->entityManager->find(ShippingStatus::class, ShippingStatus::PREPARED));
        $this->entityManager->persist($Shipping);
        $this->entityManager->flush();

        $this->client->request(
            'PUT',
            $this->generateUrl('admin_shipping_mark_as_shipped', ['id' => $Shipping->getId()]),
            ['notificationMail' => 'on']
        );

        $this->assertTrue($this->client->getResponse()->isSuccessful());

        $Messages = $this->getMailCollector(false)->getMessages();
        self::assertEquals(1, count($Messages));

        /** @var \Swift_Message $Message */
        $Message = $Messages[0];

        self::assertRegExp('/\[.*?\] 商品出荷のお知らせ/', $Message->getSubject());
        self::assertEquals([$Order->getEmail() => null], $Message->getTo());
    }
}
