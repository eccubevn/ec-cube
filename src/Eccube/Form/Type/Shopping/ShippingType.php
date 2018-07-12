<?php

/*
 * This file is part of EC-CUBE
 *
 * Copyright(c) LOCKON CO.,LTD. All Rights Reserved.
 *
 * http://www.lockon.co.jp/
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Eccube\Form\Type\Shopping;

use Eccube\Common\EccubeConfig;
use Eccube\Entity\Shipping;
use Eccube\Repository\DeliveryFeeRepository;
use Eccube\Repository\DeliveryRepository;
use Eccube\Service\ShoppingService;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * @FormType
 */
class ShippingType extends AbstractType
{
    /**
     * @var EccubeConfig
     */
    protected $eccubeConfig;

    /**
     * @var DeliveryRepository
     */
    protected $deliveryRepository;

    /**
     * @var DeliveryFeeRepository
     */
    protected $deliveryFeeRepository;

    /**
     * @var ShoppingService
     */
    protected $shoppingSerive;

    /**
     * ShippingType constructor.
     *
     * @param EccubeConfig $eccubeConfig
     * @param DeliveryRepository $deliveryRepository
     * @param DeliveryFeeRepository $deliveryFeeRepository
     * @param ShoppingService $shoppingSerive
     */
    public function __construct(
        EccubeConfig $eccubeConfig,
        DeliveryRepository $deliveryRepository,
        DeliveryFeeRepository $deliveryFeeRepository,
        ShoppingService $shoppingSerive
    ) {
        $this->eccubeConfig = $eccubeConfig;
        $this->deliveryRepository = $deliveryRepository;
        $this->deliveryFeeRepository = $deliveryFeeRepository;
        $this->shoppingSerive = $shoppingSerive;
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add(
                'OrderItems',
                CollectionType::class,
                [
                    'entry_type' => OrderItemType::class,
                ]
            );

        // 配送業者のプルダウンを生成
        $builder->addEventListener(
            FormEvents::PRE_SET_DATA,
            function (FormEvent $event) {
                /* @var Shipping $Shipping */
                $Shipping = $event->getData();
                if (is_null($Shipping) || !$Shipping->getId()) {
                    return;
                }

                // 配送商品に含まれる販売種別を抽出.
                $OrderItems = $Shipping->getProductOrderItems();
                $SaleTypes = [];
                foreach ($OrderItems as $OrderItem) {
                    $ProductClass = $OrderItem->getProductClass();
                    $SaleType = $ProductClass->getSaleType();
                    $SaleTypes[$SaleType->getId()] = $SaleType;
                }

                // 販売種別に紐づく配送業者を取得.
                $Deliveries = $this->deliveryRepository->getDeliveries($SaleTypes);
                $Deliveries = $this->shoppingSerive->filterDeliveries($Deliveries, $Shipping->getOrder());

                // 配送業者のプルダウンにセット.
                $form = $event->getForm();
                $form->add(
                    'Delivery',
                    EntityType::class,
                    [
                        'required' => false,
                        'label' => 'shipping.label.delivery_hour',
                        'class' => 'Eccube\Entity\Delivery',
                        'choice_label' => 'name',
                        'choices' => $Deliveries,
                        'placeholder' => null,
                        'constraints' => [
                            new NotBlank(),
                        ],
                    ]
                );
            }
        );

        // お届け日のプルダウンを生成
        $builder->addEventListener(
            FormEvents::PRE_SET_DATA,
            function (FormEvent $event) {
                $Shipping = $event->getData();
                if (is_null($Shipping) || !$Shipping->getId()) {
                    return;
                }

                // お届け日の設定
                $minDate = 0;
                $deliveryDurationFlag = false;

                // 配送時に最大となる商品日数を取得
                foreach ($Shipping->getOrderItems() as $detail) {
                    $ProductClass = $detail->getProductClass();
                    if (is_null($ProductClass)) {
                        continue;
                    }
                    $deliveryDuration = $ProductClass->getDeliveryDuration();
                    if (is_null($deliveryDuration)) {
                        continue;
                    }
                    if ($deliveryDuration->getDuration() < 0) {
                        // 配送日数がマイナスの場合はお取り寄せなのでスキップする
                        $deliveryDurationFlag = false;
                        break;
                    }

                    if ($minDate < $deliveryDuration->getDuration()) {
                        $minDate = $deliveryDuration->getDuration();
                    }
                    // 配送日数が設定されている
                    $deliveryDurationFlag = true;
                }

                // 配達最大日数期間を設定
                $deliveryDurations = [];

                // 配送日数が設定されている
                if ($deliveryDurationFlag) {
                    $period = new \DatePeriod(
                        new \DateTime($minDate.' day'),
                        new \DateInterval('P1D'),
                        new \DateTime($minDate + $this->eccubeConfig['eccube_deliv_date_end_max'].' day')
                    );

                    foreach ($period as $day) {
                        $deliveryDurations[$day->format('Y/m/d')] = $day->format('Y/m/d');
                    }
                }

                $form = $event->getForm();
                $form
                    ->add(
                        'shipping_delivery_date',
                        ChoiceType::class,
                        [
                            'choices' => array_flip($deliveryDurations),
                            'required' => false,
                            'placeholder' => '指定なし',
                            'mapped' => false,
                        ]
                    );
            }
        );
        // お届け時間のプルダウンを生成
        $builder->addEventListener(
            FormEvents::PRE_SET_DATA,
            function (FormEvent $event) {
                $Shipping = $event->getData();
                if (is_null($Shipping) || !$Shipping->getId()) {
                    return;
                }

                $DeliveryTimes = [];
                $Delivery = $Shipping->getDelivery();
                if ($Delivery) {
                    $DeliveryTimes = $Delivery->getDeliveryTimes();
                }

                $form = $event->getForm();
                $form->add(
                    'DeliveryTime',
                    EntityType::class,
                    [
                        'label' => 'お届け時間',
                        'class' => 'Eccube\Entity\DeliveryTime',
                        'choice_label' => 'deliveryTime',
                        'choices' => $DeliveryTimes,
                        'required' => false,
                        'placeholder' => '指定なし',
                        'mapped' => false,
                    ]
                );
            }
        );

        // POSTされないデータをエンティティにセットする.
        // TODO Calculatorで行うのが適切.
        $builder->addEventListener(FormEvents::POST_SUBMIT, function (FormEvent $event) {
            $Shipping = $event->getData();
            $Delivery = $Shipping->getDelivery();

            if ($Delivery) {
                $DeliveryFee = $this->deliveryFeeRepository->findOneBy([
                    'Delivery' => $Delivery,
                    'Pref' => $Shipping->getPref(),
                ]);

                $Shipping->setFeeId($DeliveryFee ? $DeliveryFee->getId() : null);
                $Shipping->setShippingDeliveryFee($DeliveryFee->getFee());
                $Shipping->setShippingDeliveryName($Delivery->getName());
            }
            $form = $event->getForm();
            $DeliveryTime = $form['DeliveryTime']->getData();
            if ($DeliveryTime) {
                $Shipping->setShippingDeliveryTime($DeliveryTime->getDeliveryTime());
                $Shipping->setTimeId($DeliveryTime->getId());
            }
        });
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(
            [
                'data_class' => 'Eccube\Entity\Shipping',
            ]
        );
    }

    public function getBlockPrefix()
    {
        return '_shopping_shipping';
    }
}
