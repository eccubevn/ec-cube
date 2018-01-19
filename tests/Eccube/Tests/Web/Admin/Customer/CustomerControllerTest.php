<?php

namespace Eccube\Tests\Web\Admin\Customer;

use Eccube\Entity\Master\CsvType;
use Eccube\Repository\BaseInfoRepository;
use Eccube\Repository\CustomerRepository;
use Eccube\Repository\Master\CsvTypeRepository;
use Eccube\Tests\Web\Admin\AbstractAdminWebTestCase;

/**
 * Class CustomerControllerTest
 * @package Eccube\Tests\Web\Admin\Customer
 */
class CustomerControllerTest extends AbstractAdminWebTestCase
{
    /** @var  CustomerRepository */
    protected $customerRepository;

    /** @var  BaseInfoRepository */
    protected $baseInfoRepository;

    /**
     * Setup
     */
    public function setUp()
    {
        parent::setUp();
        for ($i = 0; $i < 10; $i++) {
            $this->createCustomer('user-'.$i.'@example.com');
        }
        // sqlite では CsvType が生成されないので、ここで作る
        $CsvType = $this->container->get(CsvTypeRepository::class)->find(2);

        if (!is_object($CsvType)) {
            $CsvType = new CsvType();
            $CsvType->setId(2);
            $CsvType->setName('会員CSV');
            $CsvType->setSortNo(4);
            $this->entityManager->persist($CsvType);
            $this->entityManager->flush();
        }
        $this->customerRepository = $this->container->get(CustomerRepository::class);
        $this->baseInfoRepository = $this->container->get(BaseInfoRepository::class);
    }

    /**
     * testIndex
     */
    public function testIndex()
    {
        $this->client->request(
            'GET',
            $this->generateUrl('admin_customer')
        );
        $this->assertTrue($this->client->getResponse()->isSuccessful());
    }

    /**
     * testIndexPaging
     */
    public function testIndexPaging()
    {
        for ($i = 20; $i < 70; $i++) {
            $this->createCustomer('user-'.$i.'@example.com');
        }

        $this->client->request(
            'GET',
            $this->generateUrl('admin_customer_page', array('page_no' => 2))
        );
        $this->assertTrue($this->client->getResponse()->isSuccessful());
    }

    /**
     * testIndezWithPost
     */
    public function testIndexWithPost()
    {
        $crawler = $this->client->request(
            'POST',
            $this->generateUrl('admin_customer'),
            array('admin_search_customer' => array('_token' => 'dummy'))
        );
        $this->assertTrue($this->client->getResponse()->isSuccessful());

        $this->expected = '検索結果 10 件 が該当しました';
        $this->actual = $crawler->filter('h3.box-title')->text();
        $this->verify();
    }

    /**
     * testIndexWithPostSex
     */
    public function testIndexWithPostSex()
    {
        $crawler = $this->client->request(
            'POST',
            $this->generateUrl('admin_customer'),
            array('admin_search_customer' => array('_token' => 'dummy', 'sex' => 2))
        );
        $this->expected = '検索';
        $this->actual = $crawler->filter('h3.box-title')->text();
        $this->assertContains($this->expected, $this->actual);
    }

    /**
     * testIndexWithPostSearchByEmail
     */
    public function testIndexWithPostSearchByEmail()
    {
        $crawler = $this->client->request(
            'POST', $this->generateUrl('admin_customer'), array('admin_search_customer' => array('_token' => 'dummy', 'multi' => 'ser-7'))
        );
        $this->assertTrue($this->client->getResponse()->isSuccessful());

        $this->expected = '検索結果 1 件 が該当しました';
        $this->actual = $crawler->filter('h3.box-title')->text();
        $this->verify();
    }

    /**
     * testIndexWithPostSearchById
     */
    public function testIndexWithPostSearchById()
    {
        $Customer = $this->customerRepository->findOneBy([], array('id' => 'DESC'));

        $crawler = $this->client->request(
            'POST', $this->generateUrl('admin_customer'), array('admin_search_customer' => array('_token' => 'dummy', 'multi' => $Customer->getId()))
        );
        $this->assertTrue($this->client->getResponse()->isSuccessful());

        $this->expected = '検索結果 1 件 が該当しました';
        $this->actual = $crawler->filter('h3.box-title')->text();
        $this->verify();
    }

    /**
     * testResend
     */
    public function testResend()
    {
        $Customer = $this->createCustomer();
        $this->client->request(
            'PUT',
            $this->generateUrl('admin_customer_resend', array('id' => $Customer->getId()))
        );
        $this->assertTrue($this->client->getResponse()->isRedirect($this->generateUrl('admin_customer')));

        $mailCollector = $this->getMailCollector(false);

        $Messages = $mailCollector->getMessages();
        /** @var \Swift_Message $Message */
        $Message = $Messages[0];

        $BaseInfo = $this->baseInfoRepository->get();
        $this->expected = '['.$BaseInfo->getShopName().'] 会員登録のご確認';
        $this->actual = $Message->getSubject();
        $this->verify();

        //test mail resend to 仮会員.
        $this->assertContains($BaseInfo->getEmail02(), $Message->source);
    }

    /**
     * testDelete
     */
    public function testDelete()
    {
        $Customer = $this->createCustomer();
        $id = $Customer->getId();
        $this->client->request(
            'DELETE',
            $this->generateUrl('admin_customer_delete', array('id' => $Customer->getId()))
        );
        $this->assertTrue($this->client->getResponse()->isRedirect($this->generateUrl('admin_customer_page', array('page_no' => 1)).'?resume=1'));

        $DeletedCustomer = $this->customerRepository->find($id);

        $this->assertNull($DeletedCustomer);
    }

    /**
     * testExport
     */
    public function testExport()
    {
        $this->expectOutputRegex('/user-[0-9]@example.com/');

        $this->client->request(
            'POST',
            $this->generateUrl('admin_customer_export'),
            array('admin_search_customer' => array('_token' => 'dummy'))
        );
    }
}
