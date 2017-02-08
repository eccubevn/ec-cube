<?php
/*
 * This file is part of EC-CUBE
 *
 * Copyright(c) 2000-2015 LOCKON CO.,LTD. All Rights Reserved.
 *
 * http://www.lockon.co.jp/
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA.
 */


namespace Eccube\Tests\Web;

use Eccube\Common\Constant;

class ProductControllerTest extends AbstractWebTestCase
{

    public function testRoutingList()
    {
        $client = $this->client;
        $client->request('GET', $this->app->url('product_list'));
        $this->assertTrue($client->getResponse()->isSuccessful());
    }

    public function testRoutingDetail()
    {
        $client = $this->client;
        $client->request('GET', $this->app->url('product_detail', array('id' => '1')));
        $this->assertTrue($client->getResponse()->isSuccessful());
    }

    public function testRoutingProductFavoriteAdd()
    {
        // お気に入り商品機能を有効化
        $BaseInfo = $this->app['eccube.repository.base_info']->get();
        $BaseInfo->setOptionFavoriteProduct(Constant::ENABLED);

        $client = $this->client;
        $client->request('POST',
            $this->app->url('product_detail', array('id' => '1'))
        );
        $this->assertTrue($client->getResponse()->isSuccessful());
    }

    /**
     * testProductClassSortByRank
     */
    public function testProductClassSortByRank()
    {
        /* @var $ClassCategory \Eccube\Entity\ClassCategory */
        //set 金 rank
        $ClassCategory = $this->app['eccube.repository.class_category']->findOneBy(array('name' => '金'));
        $ClassCategory->setRank(3);
        $this->app['orm.em']->persist($ClassCategory);
        $this->app['orm.em']->flush($ClassCategory);

        //set 銀 rank
        $ClassCategory = $this->app['eccube.repository.class_category']->findOneBy(array('name' => '銀'));
        $ClassCategory->setRank(2);
        $this->app['orm.em']->persist($ClassCategory);
        $this->app['orm.em']->flush($ClassCategory);

        //set プラチナ rank
        $ClassCategory = $this->app['eccube.repository.class_category']->findOneBy(array('name' => 'プラチナ'));
        $ClassCategory->setRank(1);
        $this->app['orm.em']->persist($ClassCategory);
        $this->app['orm.em']->flush($ClassCategory);

        $client = $this->client;
        $crawler = $client->request('GET', $this->app->url('product_detail', array('id' => '1')));
        $this->assertTrue($client->getResponse()->isSuccessful());
        $classCategory = $crawler->filter('#classcategory_id1')->text();

        //選択してください, 金, 銀, プラチナ sort by rank setup above.
        $this->expected = '選択してください金銀プラチナ';
        $this->actual = $classCategory;
        $this->verify();
    }

}
