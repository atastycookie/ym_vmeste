<?php
/**
 * Created by PhpStorm.
 * Authors: Eugene Avrukevich <eugene.avrukevich@gmail.com>
 * Date: 9/25/14
 * Time: 11:54 PM
 */

namespace Vmeste\SaasBundle\Controller;


use Doctrine\ORM\Tools\Pagination\Paginator;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Cache;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Vmeste\SaasBundle\Entity\Donor;
use Vmeste\SaasBundle\Entity\Recurrent;
use Vmeste\SaasBundle\Entity\Transaction;
use Vmeste\SaasBundle\Entity\SysEvent;
use Vmeste\SaasBundle\Util\PaginationUtils;
use Vmeste\SaasBundle\Util\Rebilling;
use Vmeste\SaasBundle\Util\Clear;

/**
 * @Cache(expires="Thu, 19 Nov 1981 08:52:00 GMT", maxage=0, smaxage=0)
 */
class TransactionController extends Controller
{

    const PAYMENT_WRONG_HASH = "WRONG_HASH";
    const PAYMENT_WRONG_SHOPID = "WRONG_SHOPID";
    const PAYMENT_PENDING = "PENDING";
    const PAYMENT_COMPLETED = "COMPLETED";
    const PAYMENT_UNCOMPLETED = "UNCOMPLETED";

    public function yandexCheckAction(Request $request)
    {
        $sysEvent = new SysEvent();
        $sysEvent->setUserId(0);
        $sysEvent->setEvent(' yandex/check: ' . $request->getMethod());
        $sysEvent->setIp($this->container->get('request')->getClientIp());
        $eventTracker = $this->get('sys_event_tracker');
        $eventTracker->track($sysEvent);

        if (!$request->isMethod('POST'))  {
            throw $this->createNotFoundException();
        }

        $code = 0;
        $message = "Ok";

        $em = $this->getDoctrine()->getManager();
        $ykShopId = Clear::integer($request->request->get('shopId'));
        $ykScId = Clear::integer($request->request->get('scid'));
        $yandexKassa = $em->getRepository('Vmeste\SaasBundle\Entity\YandexKassa')->findOneBy(
            array('shopId' => $ykShopId, 'scid' => $ykScId)
        );
        if (!$yandexKassa) {
            $code = 100;
            $message = "Incorrect shopId";
        } else {
            $ykShopPassword = $yandexKassa->getShoppw();
            $stringToCalc = $request->request->get('action') . ';' . $request->request->get('orderSumAmount') . ';'
                . $request->request->get('orderSumCurrencyPaycash') . ';' . $request->request->get('orderSumBankPaycash') . ';'
                . $request->request->get('shopId') . ';' . $request->request->get('invoiceId') . ';'
                . $request->request->get('customerNumber') . ';' . $ykShopPassword;
            $hash = md5($stringToCalc);

            $sysEvent = new SysEvent();
            $sysEvent->setUserId(0);
            $sysEvent->setEvent(' yandex/check MD5 string: ' . $stringToCalc);
            $sysEvent->setIp($this->container->get('request')->getClientIp());
            $eventTracker = $this->get('sys_event_tracker');
            $eventTracker->track($sysEvent);

            $sysEvent = new SysEvent();
            $sysEvent->setUserId(0);
            $sysEvent->setEvent(' yandex/check MD5 value: ' . $hash);
            $sysEvent->setIp($this->container->get('request')->getClientIp());
            $eventTracker = $this->get('sys_event_tracker');
            $eventTracker->track($sysEvent);


            $sysEvent = new SysEvent();
            $sysEvent->setUserId(0);
            $sysEvent->setEvent(' yandex/check MD5 requested value: ' . strtolower($request->request->get('md5')));
            $sysEvent->setIp($this->container->get('request')->getClientIp());
            $eventTracker = $this->get('sys_event_tracker');
            $eventTracker->track($sysEvent);

            $orderNumber = Clear::string_without_quotes($request->request->get('orderNumber'));

            if (strcmp(strtolower($hash), strtolower($request->request->get('md5'))) !== 0) {
                $code = 1;
                $message = 'Bad md5';
            } else {
                $email = Clear::string_without_quotes($request->request->get('customerEmail', ""));
                if(empty($email)) {
                    $code = 100;
                    $message = "Empty email";
                } else {
                    $campaignId = $this->getCampaignId($orderNumber);
                    $campaign = $em->getRepository('Vmeste\SaasBundle\Entity\Campaign')->findOneBy(array('id' => $campaignId));

                    if ($campaign != null) {


                    } else {
                        $code = 200;
                        $message = "Incorrect campaing";
                    }
                }
            }
        }

        $xml = new \DOMDocument('1.0', 'utf-8');
        $checkOrderResponse = $xml->createElement('checkOrderResponse');
        $checkOrderResponse->setAttribute('performedDatetime', $request->request->get('requestDatetime'));
        $checkOrderResponse->setAttribute('code', $code);
        $checkOrderResponse->setAttribute('invoiceId', $request->request->get('invoiceId'));
        $checkOrderResponse->setAttribute('shopId', $request->request->get('shopId'));
        $checkOrderResponse->setAttribute('message', $message);
        $xml->appendChild($checkOrderResponse);
        $output = $xml->saveXML();

        $response = new Response($output, 200, array('content-type' => 'text/xml; charset=utf-8'));
        return $response;

    }

    public function yandexPaymentAvisoAction(Request $request)
    {

        $sysEvent = new SysEvent();
        $sysEvent->setUserId(0);
        $sysEvent->setEvent(' yandex/payment-aviso: ' . $request->getMethod());
        $sysEvent->setIp($this->container->get('request')->getClientIp());
        $eventTracker = $this->get('sys_event_tracker');
        $eventTracker->track($sysEvent);

        if (!$request->isMethod('POST')) {
            throw $this->createNotFoundException();
        }

        $code = 0;
        $message = "Ok";
        $em = $this->getDoctrine()->getManager();

        $ykShopId = Clear::integer($request->request->get('shopId'));
        $ykScId = Clear::integer($request->request->get('scid'));
        $yandexKassa = $em->getRepository('Vmeste\SaasBundle\Entity\YandexKassa')->findOneBy(
            array('shopId' => $ykShopId, 'scid' => $ykScId)
        );

        if (!$yandexKassa) {
            $code = 200;
            $message = "Incorrect shopId";
        } else {
            $ykShopPassword = $yandexKassa->getShoppw();

            if ($ykShopPassword) {

                $requestString = $request->request->get('action') . ';' . $request->request->get('orderSumAmount') . ';'
                    . $request->request->get('orderSumCurrencyPaycash') . ';' . $request->request->get('orderSumBankPaycash') . ';'
                    . $request->request->get('shopId') . ';' . $request->request->get('invoiceId') . ';'
                    . $request->request->get('customerNumber') . ';' . $ykShopPassword;

                $hash = md5($requestString);
                if (strcmp(strtolower($hash), strtolower($request->request->get('md5'))) === 0) {

                    $orderNumber = Clear::string_without_quotes($request->request->get('orderNumber'));

                    $postParamsArray = $this->get('request')->request->all();

                    $requestDetails = $this->createRequestString($postParamsArray);

                    $sysEvent = new SysEvent();
                    $sysEvent->setUserId(0);
                    $sysEvent->setEvent(SysEvent::CREATE_TRANSACTION . ' Request: ' . $requestDetails);
                    $sysEvent->setIp($this->container->get('request')->getClientIp());
                    $eventTracker = $this->get('sys_event_tracker');
                    $eventTracker->track($sysEvent);

                    $statusActive = $em->getRepository('Vmeste\SaasBundle\Entity\Status')->findOneBy(array('status' => 'ACTIVE'));

                    $amount = Clear::number(number_format((float)stripslashes($request->request->get('orderSumAmount')), 2, '.', ''));

                    $invoiceId = Clear::string_without_quotes($request->request->get('invoiceId'));

                    $campaignId = $this->getCampaignId($orderNumber);
                    $campaign = $em->getRepository('Vmeste\SaasBundle\Entity\Campaign')->findOneBy(array('id' => $campaignId));

                    $rb = $request->request->get('rebillingOn', false);
                    if($rb === 'false') $rb = false;
                    $donor = $existingRecurrent = false;
                    $initial = 0;
                    if($rb) {
                        $initial = 1;
                        $baseInvoice = $request->request->get('baseInvoiceId', false);
                        if($baseInvoice) {
                            $existingRecurrent = $em->getRepository('Vmeste\SaasBundle\Entity\Recurrent')->findOneBy(
                                array('invoice_id' => $baseInvoice));
                            if($existingRecurrent) {
                                $donor = $existingRecurrent->getDonor();
                                $initial = 2;
                            }
                        }
                    }

                    if(!$donor) {

                        $donorId = $this->getDonorId($orderNumber);
                        if($donorId) {
                            $donor = $em->getRepository('Vmeste\SaasBundle\Entity\Donor')->findOneBy(array('id' => $donorId));
                        } else {
                            $donor = new Donor();
                            $donor->setName(
                                Clear::string_without_quotes(
                                    $request->request->get('customerName', $orderNumber)
                                )
                            );
                            $donor->setEmail(Clear::string_without_quotes($request->request->get('customerEmail', "")));
                            $donor->setCampaignId($campaignId);
                            $donor->setDetails(Clear::string_without_quotes($request->request->get('customerComment', "")));
                            $donor->setCurrency("RUB");
                            $donor->setStatus($statusActive);
                            $donor->setAmount($amount);
                            $donor->setDates();
                            $em->persist($donor);
                            $em->flush();
                        }
                    }

                    $transaction = new Transaction();
                    $transaction->setCampaign($campaign);
                    $transaction->setDonor($donor);
                    $transaction->setInvoiceId($invoiceId);
                    $transaction->setGross($amount);
                    $transaction->setCurrency("RUB");
                    // 0 - one-off,
                    // 1 - initial,
                    // 2 - recurrent
                    $transaction->setInitial($initial);
                    $transaction->setPaymentStatus(self::PAYMENT_COMPLETED);
                    $transaction->setTransactionType(Clear::string_without_quotes($request->request->get('paymentType')));
                    $transaction->setDetails($requestDetails);
                    $em->persist($transaction);
                    $em->flush();

                    $userSettingsArray = $transaction->getCampaign()->getUser()->getSettings();
                    $settings = $userSettingsArray[0];
                    $emailFrom = $settings->getSenderEmail();

                    /**
                     *  Rebilling
                     */

                    $time = time();

                    if($existingRecurrent && !$rb) {
                        $statusDeleted = $em->getRepository('Vmeste\SaasBundle\Entity\Status')->findOneBy(array('status' => 'DELETED'));
                        $existingRecurrent->setStatus($statusDeleted);
                        $em->persist($existingRecurrent);
                        $em->flush();
                    }

                    if ($rb) {
                        $payer_email = $donor->getEmail();
                        if($existingRecurrent) {
                            $existingRecurrent->setOrderNumber($orderNumber);
                            $existingRecurrent->setSuccessDate($time);
                            $existingRecurrent->setNextDate($this->_next_date());
                            $em->persist($existingRecurrent);
                            $em->flush();

                            $rebilling = new Rebilling(
                                array('icpdo' => $em,
                                    'context' => $this,
                                    'context_mailer' => $this->get('mailer'),
                                    'context_adapter' => $this)
                            );
                            $rebilling->notify_about_successfull_monthly_payment(
                                $payer_email, $emailFrom, $settings->getCompanyName(), $amount,
                                $this->container->getParameter('recurrent.apphost') . 'outside/transaction/unsubscribe?h='
                                . $existingRecurrent->getHash());

                        } else {

                            $pan = $request->request->get('cdd_pan_mask');
                            $recurrent = new Recurrent();
                            $recurrent->setHash(md5($invoiceId));
                            $recurrent->setAmount($amount);
                            $recurrent->setCampaign($campaign);
                            $recurrent->setClientOrderId(0);
                            $recurrent->setCvv('');
                            $recurrent->setPan($pan);
                            $recurrent->setDonor($donor);
                            $recurrent->setInvoiceId($invoiceId);
                            $recurrent->setLastOperationTime($time);
                            $recurrent->setLastStatus(0);
                            $recurrent->setLastError(0);
                            $recurrent->setLastTechmessage('');
                            $recurrent->setOrderNumber($campaignId . '-' . $donor->getId() . '-' . $time);
                            $recurrent->setStatus($statusActive);
                            $recurrent->setSubscriptionDate($time);
                            $recurrent->setSuccessDate($time);

                            $recurrent->setNextDate($this->_next_date());
                            $em->persist($recurrent);
                            $em->flush();

                            // Send the first notification email
                            $rebilling = new Rebilling(
                                array('icpdo' => $em,
                                    'apphost'=> $this->container->getParameter('recurrent.apphost') ,
                                    'context' => $this,
                                    'context_mailer' => $this->get('mailer'),
                                    'context_adapter' => $this)
                            );
                            $rebilling->recurrent->email = $payer_email;
                            $rebilling->recurrent->emailFrom = $emailFrom;
                            $rebilling->recurrent->fond = $settings->getCompanyName();
                            $rebilling->recurrent->sum = $amount;
                            $rebilling->recurrent->hash = $recurrent->getHash();
                            $rebilling->recurrent->invoice = $invoiceId;
                            $rebilling->notify_about_subscription();
                        }
                    } else {
                        /*$sysEvent = new SysEvent();
                        $sysEvent->setUserId(0);
                        $sysEvent->setEvent(SysEvent::CREATE_TRANSACTION . ' LINE: ' . __LINE__);
                        $sysEvent->setIp($this->container->get('request')->getClientIp());
                        $eventTracker = $this->get('sys_event_tracker');
                        $eventTracker->track($sysEvent);*/

                        $mailMessage = \Swift_Message::newInstance()
                            ->setSubject('Спасибо за помощь!')
                            ->setFrom($emailFrom)
                            ->setTo($donor->getEmail())
                            ->setBody(
                                $this->renderView(
                                    'VmesteSaasBundle:Email:successfullPayment.html.twig',
                                    array(
                                        'name' => $donor->getName(),
                                        'amount' => $transaction->getGross(),
                                        'fond' => $settings->getCompanyName(),
                                        'yandexMoneyPage' =>
                                            $this->container->getParameter('recurrent.apphost')
                                            . $transaction->getCampaign()->getUrl())
                                )
                            );
                        $this->get('mailer')->send($mailMessage);
                    }

                    /*$sysEvent = new SysEvent();
                    $sysEvent->setUserId(0);
                    $sysEvent->setEvent(SysEvent::CREATE_TRANSACTION . ' LINE: ' . __LINE__);
                    $sysEvent->setIp($this->container->get('request')->getClientIp());
                    $eventTracker = $this->get('sys_event_tracker');
                    $eventTracker->track($sysEvent);*/

                } else {
                    $code = 1;
                    $message = 'Bad md5';
                    /*$sysEvent = new SysEvent();
                    $sysEvent->setUserId(0);
                    $sysEvent->setEvent(SysEvent::CREATE_TRANSACTION . ' ' . $message);
                    $sysEvent->setIp($this->container->get('request')->getClientIp());
                    $eventTracker = $this->get('sys_event_tracker');
                    $eventTracker->track($sysEvent);*/
                }
            } else {
                $code = 200;
                $message = 'Bad shopPassword';
                /*$sysEvent = new SysEvent();
                $sysEvent->setUserId(0);
                $sysEvent->setEvent(SysEvent::CREATE_TRANSACTION . ' ' . $message);
                $sysEvent->setIp($this->container->get('request')->getClientIp());
                $eventTracker = $this->get('sys_event_tracker');
                $eventTracker->track($sysEvent);*/
            }
        }

        $xml = new \DOMDocument('1.0', 'utf-8');
        $paymentAvisoResponse = $xml->createElement('paymentAvisoResponse');
        $paymentAvisoResponse->setAttribute('performedDatetime', date('Y-m-d\TH:i:s.000P', time()));
        $paymentAvisoResponse->setAttribute('shopId', $request->request->get('shopId'));
        $paymentAvisoResponse->setAttribute('invoiceId', $request->request->get('invoiceId'));
        $paymentAvisoResponse->setAttribute('code', $code);
        $paymentAvisoResponse->setAttribute('message', $message);
        $xml->appendChild($paymentAvisoResponse);
        $output = $xml->saveXML();

        $sysEvent = new SysEvent();
        $sysEvent->setUserId(0);
        $sysEvent->setEvent(SysEvent::CREATE_TRANSACTION . ' paymentAviso output: ' . $output);
        $sysEvent->setIp($this->container->get('request')->getClientIp());
        $eventTracker = $this->get('sys_event_tracker');
        $eventTracker->track($sysEvent);

        $response = new Response($output, 200, array('content-type' => 'text/xml; charset=utf-8'));
        return $response;
    }

    private function _next_date()
    {
        $day = date('j');
        $month = date('n') + 1;
        $year = date('Y');
        if($month > 12) {
            $month = 1;
            $year += 1;
        }
        if($day>28) $day = 28;
        return mktime(12, 0, 0, $month, $day, $year);
    }

    /**
     * @Template
     */
    public function homeAction()
    {
        return array('data' => true);
    }

    /**
     * @Template
     */
    public function unsubscribeAction()
    {
        $recurrent_hash = Clear::string_without_quotes($this->getRequest()->query->get("h"));
        $response = array('error' => false,
            'h' => $recurrent_hash,
            'title' => '',
            'intro' => '',
            'img' => '');

        $sysEvent = new SysEvent();
        $sysEvent->setUserId(0);
        $sysEvent->setEvent(SysEvent::UNSUBSCRIBE_RECURRENT . " request $recurrent_hash");
        $sysEvent->setIp($this->container->get('request')->getClientIp());
        $eventTracker = $this->get('sys_event_tracker');
        $eventTracker->track($sysEvent);

        if ($recurrent_hash == null) {
            $response['error'] = 'Неверные параметры';
            return $response;
        }

        $em = $this->getDoctrine()->getManager();
        $recurrent = $em->getRepository('Vmeste\SaasBundle\Entity\Recurrent')->findOneBy(array('hash'=>$recurrent_hash));
        if ($recurrent == null) {
            $response['error'] = 'Такой подписки не существует';
            return $response;
        }

        $donor = $recurrent->getDonor();
        if ($donor == null) {
            $response['error'] = 'Такой подписки не существует';
            return $response;
        }

        $campaignId = $donor->getCampaignId();
        $campaign = $em->getRepository('Vmeste\SaasBundle\Entity\Campaign')->find($campaignId);
        if ($campaign == null) {
            $response['error'] = 'Такой подписки не существует';
            return $response;
        }

        $user = $campaign->getUser();
        $userSettingsArray = $user->getSettings();
        $userLogoPath = $user->getLogoPath();
        $settings = $userSettingsArray[0];
        $response['title'] = $campaign->getSubTitle();
        $imageStoragePath = $this->container->getParameter('image.upload.dir');
        $response['img'] = $imageStoragePath.$userLogoPath; //$imageStoragePath.$campaign->getBigPicPath();
        $response['fond'] = $settings->getCompanyName();
        $response['campaign_url'] = $this->container->getParameter('recurrent.apphost').$campaign->getUrl();


        if (md5($recurrent->getInvoiceId()) != $recurrent_hash) {
            $response['error'] = 'Такой подписки не существует';
            return $response;
        }

        if ((int)$this->getRequest()->query->get("yes") == 1) {
            $status = $em->getRepository('Vmeste\SaasBundle\Entity\Status')->findOneBy(array('status' => 'DELETED'));
            $recurrent->setStatus($status);
            $em->persist($recurrent);
            $em->flush();

            $sysEvent->setEvent(SysEvent::UNSUBSCRIBE_RECURRENT . " done $recurrent_hash");
            $eventTracker->track($sysEvent);

            return $this->render('VmesteSaasBundle:Transaction:unsubscribe_success.html.twig', $response);
        } elseif ((int)$this->getRequest()->query->get("yes") == 2) {
            return $this->render('VmesteSaasBundle:Transaction:unsubscribe_decline.html.twig', $response);
        }
        return $response;
    }

    /**
     * @Template
     */
    public function subscribeAction()
    {
        $recurrent_hash = Clear::string_without_quotes($this->getRequest()->query->get("h"));
        $response = array('error' => false,
            'h' => $recurrent_hash,
            'title' => '',
            'intro' => '',
            'img' => '');


        $sysEvent = new SysEvent();
        $sysEvent->setUserId(0);
        $sysEvent->setEvent(SysEvent::SUBSCRIBE_RECURRENT . " request $recurrent_hash");
        $sysEvent->setIp($this->container->get('request')->getClientIp());
        $eventTracker = $this->get('sys_event_tracker');
        $eventTracker->track($sysEvent);

        if ($recurrent_hash == null) {
            $response['error'] = 'Неверные параметры';
            return $response;
        }

        $em = $this->getDoctrine()->getManager();
        $recurrent = $em->getRepository('Vmeste\SaasBundle\Entity\Recurrent')->findOneBy(array('hash'=>$recurrent_hash));
        if ($recurrent == null) {
            $response['error'] = 'Такой подписки не существует';
            return $response;
        }

        $donor = $recurrent->getDonor();
        if ($donor == null) {
            $response['error'] = 'Такой подписки не существует';
            return $response;
        }

        $campaignId = $donor->getCampaignId();
        $campaign = $em->getRepository('Vmeste\SaasBundle\Entity\Campaign')->find($campaignId);
        if ($campaign == null) {
            $response['error'] = 'Такой подписки не существует';
            return $response;
        }

        $response['title'] = $campaign->getTitle();
        $imageStoragePath = $this->container->getParameter('image.upload.dir');
        $response['img'] = $imageStoragePath.$campaign->getBigPicPath();
        $response['intro'] = $campaign->getFormIntro();


        if (md5($recurrent->getInvoiceId()) != $recurrent_hash) {
            $response['error'] = 'Такой подписки не существует';
            return $response;
        }

        $status = $em->getRepository('Vmeste\SaasBundle\Entity\Status')->findOneBy(array('status' => 'ACTIVE'));
        $recurrent->setStatus($status);
        $em->persist($recurrent);
        $em->flush();

        $sysEvent->setEvent(SysEvent::SUBSCRIBE_RECURRENT . " done $recurrent_hash");
        $eventTracker->track($sysEvent);

        return $this->render('VmesteSaasBundle:Transaction:unsubscribe_decline.html.twig', $response);
    }

    /**
     * @Template
     */
    public function reportAction()
    {

        $limit = $this->container->getParameter('paginator.page.items');
        $pageOnSidesLimit = 10;

        $page = $this->getRequest()->query->get("page", 1);

        $em = $this->getDoctrine()->getManager();

        $user = $this->get('security.context')->getToken()->getUser();
        $sysEvent = new SysEvent();
        $sysEvent->setUserId($user->getId());
        $sysEvent->setEvent(SysEvent::REPORT_ALL_TRANSACTIONS);
        $sysEvent->setIp($this->container->get('request')->getClientIp());
        $eventTracker = $this->get('sys_event_tracker');
        $eventTracker->track($sysEvent);

        $queryBuilder = $em->createQueryBuilder();

        $queryBuilder->select('t')->from('Vmeste\SaasBundle\Entity\Transaction', 't')
            ->innerJoin('Vmeste\SaasBundle\Entity\Campaign', 'c', 'WITH', 't.campaign = c')
            ->orderBy('t.changed', 'DESC');

        $queryBuilder->setFirstResult(($page - 1) * $limit)->setMaxResults($limit);

        $paginator = new Paginator($queryBuilder, $fetchJoinCollection = false);

        $totalItems = count($paginator);

        $pageCount = (int)ceil($totalItems / $limit);

        $pageNumberArray = PaginationUtils::generatePaginationPageNumbers($page, $pageOnSidesLimit, $pageCount);

        return array(
            'transactions' => $paginator,
            'pages' => $pageNumberArray,
            'page' => $page,
        );
    }

    /**
     * @Template
     */
    public function searchAction()
    {

        if ($this->getRequest()->query->get("searchRequest", null) != null) {

            $searchRequest = $this->getRequest()->query->get("searchRequest", null);

            $limit = $this->container->getParameter('paginator.page.items');
            $pageOnSidesLimit = 2;

            $page = $this->getRequest()->query->get("page", 1);

            $em = $this->getDoctrine()->getManager();

            $currentUser = $this->get('security.context')->getToken()->getUser();

            $user = $em->getRepository('Vmeste\SaasBundle\Entity\User')->findOneBy(array('id' => $currentUser->getId()));

            $user = $this->get('security.context')->getToken()->getUser();
            $sysEvent = new SysEvent();
            $sysEvent->setUserId($user->getId());
            $sysEvent->setEvent(SysEvent::SEARCH_TRANSACTION);
            $sysEvent->setIp($this->container->get('request')->getClientIp());
            $eventTracker = $this->get('sys_event_tracker');
            $eventTracker->track($sysEvent);

            $queryBuilder = $em->createQueryBuilder();

            $queryBuilder->select('t')->from('Vmeste\SaasBundle\Entity\Transaction', 't')
                ->innerJoin('Vmeste\SaasBundle\Entity\Donor', 'd', 'WITH', 't.donor = d')
                ->innerJoin('Vmeste\SaasBundle\Entity\Campaign', 'c', 'WITH', 't.campaign = c')
                ->where('d.name LIKE :name OR d.email LIKE :email OR t.invoiceId = :invoiceId')
                ->setParameter('name', '%' . $searchRequest . '%')
                ->setParameter('email', '%' . $searchRequest . '%')
                ->setParameter('invoiceId', $searchRequest);


            $queryBuilder->setFirstResult(($page - 1) * $limit)->setMaxResults($limit);

            $paginator = new Paginator($queryBuilder, $fetchJoinCollection = false);

            $totalItems = count($paginator);

            $pageCount = (int)ceil($totalItems / $limit);

            $pageNumberArray = PaginationUtils::generatePaginationPageNumbers($page, $pageOnSidesLimit, $pageCount);


            return array(
                'transactions' => $paginator,
                'pages' => $pageNumberArray,
                'page' => $page,
                'searchRequest' => $searchRequest
            );
        } else {
            return $this->redirect($this->generateUrl('transaction_report'));
        }


    }

    public function reportExportAction()
    {
        $startTimestamp = $endTimestamp = 0;

        if ($this->getRequest()->query->get("start", null) != null
            && $this->getRequest()->query->get("end", null) != null
        ) {
            $start = $this->getRequest()->query->get("start");
            $end = $this->getRequest()->query->get("end");
            $startTimestamp = $this->parseDateToTimestamp($start);
            $endTimestamp = $this->parseDateToTimestamp($end);
        }

        $user = $this->get('security.context')->getToken()->getUser();
        $sysEvent = new SysEvent();
        $sysEvent->setUserId($user->getId());
        $sysEvent->setEvent(SysEvent::REPORT_TRANSACTIONS_BY_DATE);
        $sysEvent->setIp($this->container->get('request')->getClientIp());
        $eventTracker = $this->get('sys_event_tracker');
        $eventTracker->track($sysEvent);

        $em = $this->getDoctrine()->getManager();
        $queryBuilder = $em->createQueryBuilder();

        $queryBuilder->select('t')->from('Vmeste\SaasBundle\Entity\Transaction', 't')
            ->innerJoin('Vmeste\SaasBundle\Entity\Campaign', 'c', 'WITH', 't.campaign = c')
            ->orderBy('t.changed', 'DESC');

        if ($startTimestamp != 0 && $endTimestamp != 0) {
            $queryBuilder->andWhere('t.created >= ?1');
            $queryBuilder->andWhere('t.created <= ?2');
            $queryBuilder->setParameter(1, $startTimestamp);
            $queryBuilder->setParameter(2, $endTimestamp);
        }

        $report = $queryBuilder->getQuery()->getResult();

        $responseHeaders = array();

        if (strstr($this->getRequest()->server->get('HTTP_USER_AGENT'), "MSIE")) {
            $responseHeaders['pragma'] = 'public';
            $responseHeaders['expires'] = '0';
            $responseHeaders['cache-control'] = 'must-revalidate, post-check=0, pre-check=0';
            $responseHeaders['content-type'] = 'text/csv; charset=utf-8';
            $responseHeaders['content-disposition'] = 'attachment; filename="export-transactions' . date("Y-m-d") . '.csv"';
            $responseHeaders['content-transfer-encoding'] = 'binary';
        } else {
            $responseHeaders['content-type'] = 'text/csv; charset=utf-8';
            $responseHeaders['content-disposition'] = 'attachment; filename="export-transactions' . date("Y-m-d") . '.csv"';
        }

        $separator = ';';

        if ($separator == 'tab') $separator = "\t";

        $output = chr(0xEF) . chr(0xBB) . chr(0xBF) . '"Проект"' . $separator
            . '"ФИО"' . $separator
            . '"Email"' . $separator
            . '"Сумма"' . $separator
            . '"Дата платежа"' . $separator
            . '"Способ оплаты"' . $separator
            . '"Статус"' . $separator
            . '"Признак подписчика"' . $separator
            . '"Инитный"' . $separator
            . '"Комментарии"' . $separator . "\r\n";

        foreach ($report as $transaction) {
            $output .= '"'
                . str_replace('"', '', $transaction->getCampaign()->getTitle()) . '"' . $separator . '"'
                . str_replace('"', '', $transaction->getDonor()->getName()) . '"' . $separator . '"'
                . str_replace('"', "", $transaction->getDonor()->getEmail()) . '"' . $separator . '"'
                . str_replace('"', "", $transaction->getGross()) . '"' . $separator . '"'
                . str_replace('"', '', date("Y-m-d H:i:s", $transaction->getCreated())) . '"' . $separator . '"'
                . str_replace('"', "", $transaction->getTransactionType()) . '"' . $separator . '"'
                . str_replace('"', "", $transaction->getPaymentStatus()) . '"' . $separator . '"';
            $transaction->getDonor()->getRecurrent() != null ? $output .= '1' : $output .= '0';
            $initial = $transaction->getInitial();
            $output .= '"' . $separator . '"';
            switch($initial) {
                case 1: $output .= 'инитный'; break;
                case 2: $output .= 'повтор'; break;
                default: $output .= ''; break;
            }
            $output .= '"' . $separator . '"' . str_replace('"', "", $transaction->getDonor()->getDetails()) . '"' . "\r\n";
        }

        $response = new Response($output, 200, $responseHeaders);
        return $response;
    }

    /**
     * @param $postParamsArray
     * @return string
     */
    private function createRequestString($postParamsArray)
    {
        $response = "";
        foreach ($postParamsArray as $key => $value) {
            $value = urlencode(stripslashes($value));
            $response .= "&" . $key . "=" . $value;
        }
        return $response;
    }

    /**
     * @param $orderNumber
     * @return mixed
     */
    private function getCampaignId($orderNumber)
    {
        $campaignId = false;
        $orderNumberExploded = explode("-", $orderNumber);
        if(is_array($orderNumberExploded) && isset($orderNumberExploded[0])) $campaignId = $orderNumberExploded[0];
        return $campaignId;
    }

    /**
     * @param $orderNumber
     * @return mixed
     */
    private function getDonorId($orderNumber)
    {
        $donorId = false;
        $orderNumberExploded = explode("-", $orderNumber);
        if(is_array($orderNumberExploded) && count($orderNumberExploded) == 3 && isset($orderNumberExploded[1]))
            $donorId = $orderNumberExploded[1];
        return $donorId;
    }

    /**
     * @param $dateStr
     * @return int
     */
    private function parseDateToTimestamp($dateStr)
    {
        $a = date_parse_from_format('Y-m-d', $dateStr);
        $timestamp = mktime(0, 0, 0, $a['month'], $a['day'], $a['year']);
        return $timestamp;
    }
} 