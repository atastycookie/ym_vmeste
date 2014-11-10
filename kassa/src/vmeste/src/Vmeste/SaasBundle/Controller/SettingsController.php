<?php
/**
 * Created by PhpStorm.
 * Authors: Eugene Avrukevich <eugene.avrukevich@gmail.com>
 * Date: 9/23/14
 * Time: 9:44 PM
 */

namespace Vmeste\SaasBundle\Controller;


use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Vmeste\SaasBundle\Entity\Settings;
use Vmeste\SaasBundle\Entity\User;
use Vmeste\SaasBundle\Entity\SysEvent;
use Vmeste\SaasBundle\Util\Hash;
use Vmeste\SaasBundle\Util\Clear;

class SettingsController extends Controller
{

    /**
     * @Template
     */
    public function editSettingsAction($errors = null)
    {
        $userId = Clear::integer($this->getRequest()->get('userId', null), null);

        $emailSettingsErrors = null;
        if (count($this->get('session')->getFlashBag()->peek('email_setting_errors')) > 0) {
            $emailSettingsErrorsArray = $this->get('session')->getFlashBag()->get('email_setting_errors');
            $emailSettingsErrors = $emailSettingsErrorsArray[0];
        }

        $passwordSettingsErrors = null;
        if (count($this->get('session')->getFlashBag()->peek('password_setting_errors')) > 0) {
            $passwordSettingsErrorsArray = $this->get('session')->getFlashBag()->get('password_setting_errors');
            $passwordSettingsErrors = $passwordSettingsErrorsArray[0];
        }

        $fileErrors = null;
        if (count($this->get('session')->getFlashBag()->peek('file_errors')) > 0) {
            $fileErrorsArray = $this->get('session')->getFlashBag()->get('file_errors');
            $fileErrors = $fileErrorsArray[0];
        }

        if ($userId == null) {
            $authorizedUser = $this->get('security.context')->getToken()->getUser();
            $userId = $authorizedUser->getId();
        }

        $em = $this->getDoctrine()->getManager();
        $user = $em->getRepository('Vmeste\SaasBundle\Entity\User')->findOneBy(array('id' => intval($userId)));

        if ($user == null) {
            return $this->redirect($this->generateUrl("vmeste_saas"));
        } else {
            $settingsCollection = $user->getSettings();
            $userSettings = $settingsCollection[0];
            $yandexKassa = $userSettings->getYandexKassa();
        }


        $updateEmailSettingsRoute = $this->generateUrl('update_email_settings');
        $updateYKSettingsRoute = $this->generateUrl('update_yk_settings');
        $updatePasswordRoute = $this->generateUrl('update_customer_password');

        $userIdForEdit = null;

        if (($this->get('security.context')->isGranted('ROLE_ADMIN'))) {
            $updateEmailSettingsRoute = $this->generateUrl('admin_update_email_settings');
            $updateYKSettingsRoute = $this->generateUrl('admin_update_yk_settings');
            $updatePasswordRoute = $this->generateUrl('admin_update_customer_password');
            $userIdForEdit = $userId;
        }

        return array(
            'email_setting_errors' => $emailSettingsErrors,
            'notification_email' => $userSettings->getNotificationEmail(),
            'company_name' => $userSettings->getCompanyName(),
            'director_name' => $userSettings->getDirectorName(),
            'position' => $userSettings->getPosition(),
            'authority' => $userSettings->getAuthority(),
            'details' => $userSettings->getDetails(),
            'sender_name' => $userSettings->getSenderName(),
            'sender_email' => $userSettings->getSenderEmail(),
            'csv_separator' => $userSettings->getCsvColumnSeparator(),
            'shopid' => $yandexKassa->getShopId(),
            'scid' => $yandexKassa->getScid(),
            'shoppassword' => $yandexKassa->getShoppw(),
            'pc' => $yandexKassa->getPc(),
            'ac' => $yandexKassa->getAc(),
            'wm' => $yandexKassa->getWm(),
            'mc' => $yandexKassa->getMc(),
            'gp' => $yandexKassa->getGp(),
            'sandbox' => $yandexKassa->getSandbox(),
            /*'certificate' => $yandexKassa->getCertFilePath(),
            'certkey' => $yandexKassa->getCertKeyFilePath(),
            'certpass' => $yandexKassa->getCertPass(),*/
            'password_setting_errors' => $passwordSettingsErrors,
            'updateEmailSettingsRoute' => $updateEmailSettingsRoute,
            'updateYKSettingsRoute' => $updateYKSettingsRoute,
            'updatePasswordRoute' => $updatePasswordRoute,
            'userIdForEdit' => $userIdForEdit,
            'fileErrors' => $fileErrors
        );
    }

    public function updateEmailSettingsAction()
    {
        $request = $this->getRequest();

        $redirectUri = $this->generateUrl('vmeste_saas');

        if ($request->isMethod('POST')) {

            $companyName = Clear::removeCRLF($request->request->get('company_name'));
            $director_name = Clear::string_without_quotes($request->request->get('director_name'));
            $position = Clear::string_without_quotes($request->request->get('position'));
            $authority = Clear::string_without_quotes($request->request->get('authority'));
            $details = Clear::string_without_quotes($request->request->get('details'));
            $notificationEmail = Clear::string_without_quotes($request->request->get('notification_email'));
            $senderName = Clear::string_without_quotes($request->request->get('sender_name'));
            $senderEmail = Clear::string_without_quotes($request->request->get('sender_email'));
            $csvSeparator = Clear::string_without_quotes($request->request->get('csv_separator'));

            $notificationEmailConstraint = new Email();
            $notificationEmailConstraint->message = "The email " . $notificationEmail . ' is not a valid email';
            $notificationEmailErrorList = $this->get('validator')->validateValue($notificationEmail, $notificationEmailConstraint);

            $companyNameConstraint = new Length(array('min' => 2, 'max' => 255));
            $companyNameErrorList = $this->get('validator')->validateValue($companyName, $companyNameConstraint);

            $directorNameConstraint = new Length(array('min' => 3, 'max' => 255));
            $directorNameErrorList = $this->get('validator')->validateValue($director_name, $directorNameConstraint);

            $positionConstraint = new Length(array('min' => 3, 'max' => 512));
            $positionErrorList = $this->get('validator')->validateValue($position, $positionConstraint);

            $authorityConstraint = new Length(array('min' => 3, 'max' => 512));
            $authorityErrorList = $this->get('validator')->validateValue($authority, $authorityConstraint);

            $detailsConstraint = new Length(array('min' => 25, 'max' => 1024));
            $detailsErrorList = $this->get('validator')->validateValue($details, $detailsConstraint);

            $senderNameConstraint = new Length(array('min' => 2, 'max' => 255));
            $senderNameEmailErrorList = $this->get('validator')->validateValue($senderName, $senderNameConstraint);

            $senderEmailConstraint = new Email();
            $senderEmailConstraint->message = "The email " . $senderEmail . ' is not a valid email';
            $senderEmailErrorList = $this->get('validator')->validateValue($senderEmail, $senderEmailConstraint);

            $availableSeparators = array(';', ',', 'tab');

            $userId = $this->getRequest()->get('userId', null);

            if (count($notificationEmailErrorList) == 0 && count($senderNameEmailErrorList) == 0
                && count($senderEmailErrorList) == 0 && in_array($csvSeparator, $availableSeparators)
                && count($companyNameErrorList) == 0
                && count($detailsErrorList) == 0
                && count($directorNameErrorList) == 0
            ) {

                $em = $this->getDoctrine()->getManager();

                if (!($this->get('security.context')->isGranted('ROLE_ADMIN'))) {
                    $currentUser = $this->get('security.context')->getToken()->getUser();
                    $userId = $currentUser->getId();
                }

                $user = $em->getRepository('Vmeste\SaasBundle\Entity\User')->findOneBy(array('id' => $userId));

                $settingsCollection = $user->getSettings();
                $userSettings = $settingsCollection[0];

                $userSettings->setCompanyName($companyName);
                $userSettings->setDetails($details);
                $userSettings->setDirectorName($director_name);
                $userSettings->setPosition($position);
                $userSettings->setAuthority($authority);
                $userSettings->setNotificationEmail($notificationEmail);
                $userSettings->setSenderName($senderName);
                $userSettings->setSenderEmail($senderEmail);
                $userSettings->setCsvColumnSeparator($csvSeparator);

                $em->persist($userSettings);
                $em->flush();

                $userEvent = $this->get('security.context')->getToken()->getUser();
                $sysEvent = new SysEvent();
                $sysEvent->setUserId($userEvent->getId());
                $sysEvent->setEvent(SysEvent::UPDATE_EMAIL_SETTINGS . " of user " . $userId);
                $sysEvent->setIp($this->container->get('request')->getClientIp());
                $eventTracker = $this->get('sys_event_tracker');
                $eventTracker->track($sysEvent);

                $redirectUri = $this->generateUrl('customer_settings');

                if (($this->get('security.context')->isGranted('ROLE_ADMIN'))) {
                    $redirectUri = $this->generateUrl('admin_customer_settings', array('userId' => $userId));
                }

            } else {

                $errorList = "<ul>";
                $errorList .= count($companyNameErrorList) > 0 ? "<li>" . $companyNameErrorList[0]->getMessage() . "</li>" : "";
                $errorList .= count($directorNameErrorList) > 0 ? "<li>" . $directorNameErrorList[0]->getMessage() . "</li>" : "";
                $errorList .= count($positionErrorList) > 0 ? "<li>" . $positionErrorList[0]->getMessage() . "</li>" : "";
                $errorList .= count($authorityErrorList) > 0 ? "<li>" . $authorityErrorList[0]->getMessage() . "</li>" : "";
                $errorList .= count($detailsErrorList) > 0 ? "<li>" . $detailsErrorList[0]->getMessage() . "</li>" : "";
                $errorList .= count($notificationEmailErrorList) > 0 ? "<li>" . $notificationEmailErrorList[0]->getMessage() . "</li>" : "";
                $errorList .= count($senderNameEmailErrorList) > 0 ? "<li>" . $senderNameEmailErrorList[0]->getMessage() . "</li>" : "";
                $errorList .= count($senderEmailErrorList) > 0 ? "<li>" . $senderEmailErrorList[0]->getMessage() . "</li>" : "";
                $errorList .= !in_array($csvSeparator, $availableSeparators) ? "<li>Separator, you have choosen is forbidden.</li>" : "";
                $errorList .= "</ul>";

                $this->get('session')->getFlashBag()->add('email_setting_errors', $errorList);

                $redirectUri = $this->generateUrl('customer_settings');

                if (($this->get('security.context')->isGranted('ROLE_ADMIN'))) {
                    $redirectUri = $this->generateUrl('admin_customer_settings', array('userId' => $userId));
                }
            }
        }

        return $this->redirect($redirectUri);
    }

    public function updateYkSettingsAction()
    {

        $request = $this->getRequest();

        $redirectUri = $this->generateUrl('vmeste_saas');

        if ($request->isMethod('POST')) {

            $shopid = Clear::integer($request->request->get('yandex_shopid', NULL), null);
            $scid = Clear::integer($request->request->get('yandex_scid', NULL), null);
            $shoppw = Clear::removeCRLF($request->request->get('yandex_shoppw', NULL));

            $pc = $this->convertCheckboxDataToInt($request->request->get('yandex_pt_pc'));
            $ac = $this->convertCheckboxDataToInt($request->request->get('yandex_pt_ac'));
            $wm = $this->convertCheckboxDataToInt($request->request->get('yandex_pt_wm'));
            $mc = $this->convertCheckboxDataToInt($request->request->get('yandex_pt_mc'));
            $gp = $this->convertCheckboxDataToInt($request->request->get('yandex_pt_gp'));
            $sandbox = $this->convertCheckboxDataToInt($request->get('yandex_sandbox'));

            $certFile = $request->files->get('cert_file', NULL);
            $certKeyFile = $request->files->get('cert_key_file', NULL);
            $certPass = $request->request->get('cert_pass', NULL);

            $fileConstraint = new File(
                array(
                    'maxSize' => "1M",
                    'mimeTypes' => array(
                        'text/plain',
                        'application/pkix-cert',
                        'application/x-pem-file',
                        'application/x-x509-ca-cert',
                        'application/x-x509-user-cert',
                        'application/pkcs8',
                        'text/plain'
                    )
                ));

            $fileConstraint->maxSizeMessage = "Недопустимый размер файла. Максимальный размер 1мб";

            $fileConstraint->mimeTypesMessage = "Неверный тип файла сертификата";

            $certFileConstraintErrorList = NULL;
            if ($certFile != NULL)
                $certFileConstraintErrorList = $this->get('validator')
                    ->validateValue($certFile, $fileConstraint);

            $fileConstraint->mimeTypesMessage = "Неверный тип файла ключа";

            $certKeyFileConstraintErrorList = NULL;
            if ($certKeyFile != NULL)
                $certKeyFileConstraintErrorList = $this->get('validator')
                    ->validateValue($certKeyFile, $fileConstraint);

            if (count($certFileConstraintErrorList) != 0 || count($certKeyFileConstraintErrorList) != 0) {
                $fileErrors = '<ul>';
                foreach ($certFileConstraintErrorList as $certFileError) {
                    $message = $certFileError->getMessage();
                    if (!empty($message))
                        $fileErrors .= '<li>' . $message . '</li>';
                }


                foreach ($certKeyFileConstraintErrorList as $certKeyFileError) {
                    $message = $certKeyFileError->getMessage();
                    if (!empty($message))
                        $fileErrors .= '<li>' . $message . '</li>';
                }

                $fileErrors .= '</ul>';

                $this->get('session')->getFlashBag()->add('file_errors', $fileErrors);

                $currentUser = $this->get('security.context')->getToken()->getUser();
                $userId = $currentUser->getId();

                if (($this->get('security.context')->isGranted('ROLE_ADMIN'))) {
                    $userId = $this->getRequest()->get('userId', null);
                }

                $redirectUri = $this->generateUrl('customer_settings');

                if (($this->get('security.context')->isGranted('ROLE_ADMIN'))) {
                    $redirectUri = $this->generateUrl('admin_customer_settings', array('userId' => $userId));
                }

            } else { // if no file errors founded

                $em = $this->getDoctrine()->getManager();

                $currentUser = $this->get('security.context')->getToken()->getUser();
                $userId = $currentUser->getId();
                if (($this->get('security.context')->isGranted('ROLE_ADMIN'))) {
                    $userId = $this->getRequest()->get('userId', null);
                }

                $user = $em->getRepository('Vmeste\SaasBundle\Entity\User')->findOneBy(array('id' => $userId));

                $settingsCollection = $user->getSettings();
                $userSettings = $settingsCollection[0];

                $yandexKassa = $userSettings->getYandexKassa();

                $yandexKassa->setShopId($shopid);
                $yandexKassa->setScid($scid);

                if (empty($shoppw))
                    $shoppw = $yandexKassa->getShoppw();

                $yandexKassa->setShoppw($shoppw);

                $yandexKassa->setPc($pc);
                $yandexKassa->setAc($ac);
                $yandexKassa->setWm($wm);
                $yandexKassa->setMc($mc);
                $yandexKassa->setGp($gp);
                $yandexKassa->setSandbox($sandbox);

                $yandexKassa->setCertFile($certFile);
                $yandexKassa->setCertKeyFile($certKeyFile);


                if (empty($certPass))
                    $certPass = $yandexKassa->getCertPass();

                $yandexKassa->setCertPass($certPass);

                $yandexKassa->upload();


                $em->persist($yandexKassa);
                $em->flush();

                $userEvent = $this->get('security.context')->getToken()->getUser();
                $sysEvent = new SysEvent();
                $sysEvent->setUserId($userEvent->getId());
                $sysEvent->setEvent(SysEvent::UPDATE_YK_SETTINGS . " of user " . $userId);
                $sysEvent->setIp($this->container->get('request')->getClientIp());
                $eventTracker = $this->get('sys_event_tracker');
                $eventTracker->track($sysEvent);

                $redirectUri = $this->generateUrl('customer_settings');

                if (($this->get('security.context')->isGranted('ROLE_ADMIN'))) {
                    $redirectUri = $this->generateUrl('admin_customer_settings', array('userId' => $userId));
                }
            }

        }

        return $this->redirect($redirectUri);
    }

    private function convertCheckboxDataToInt($data)
    {
        return $data == 'on' ? 1 : 0;
    }

    public function updatePasswordAction()
    {

        $errorList = "";

        $logger = $this->get('logger');

        $request = $this->get('request');

        $redirectUri = $this->generateUrl('vmeste_saas');

        if ($request->isMethod('POST')) {


            $notBlank = new NotBlank();
            $length = new Length(array('min' => 6, 'max' => 128));

            $validator = $this->get('validator');

            if (!($this->get('security.context')->isGranted('ROLE_ADMIN'))) {
                $oldPassword = $request->request->get('old_password');
                $notBlank->message = "Введите старый пароль!";
                $oldPasswordErrorList = $validator->validateValue($oldPassword, array($notBlank, $length));
            } else {
                $oldPasswordErrorList = array();
            }

            $password = $request->request->get('new_password');
            $notBlank->message = "Введите новый пароль!";
            $passwordErrorList = $validator->validateValue($password, array($notBlank, $length));

            $passwordRepeat = $request->request->get('new_password_repeat');
            $notBlank->message = "Введите новый пароль повторно!";
            $passwordRepeatErrorList = $validator->validateValue($passwordRepeat, array($notBlank, $length));

            $passwordsEqual = ($password === $passwordRepeat);

            $userId = $this->getRequest()->get('userId', null);
            $searchParamsArray = array('id' => $userId);

            if (count($oldPasswordErrorList) == 0 && count($passwordErrorList) == 0
                && count($passwordRepeatErrorList) == 0 && $passwordsEqual
            ) {

                $doctrine = $this->getDoctrine();
                $em = $doctrine->getManager();

                if (!($this->get('security.context')->isGranted('ROLE_ADMIN'))) {

                    $factory = $this->get('security.encoder_factory');
                    $user = new User();
                    $encoder = $factory->getEncoder($user);
                    $oldPassword = $encoder->encodePassword($oldPassword, $user->getSalt());

                    $currentUser = $this->get('security.context')->getToken()->getUser();
                    $userId = $currentUser->getId();
                    $searchParamsArray = array('id' => $userId, 'password' => $oldPassword);
                }

                $user = $this->getDoctrine()
                    ->getRepository('Vmeste\SaasBundle\Entity\User')
                    ->findOneBy($searchParamsArray);

                if ($user != null) {

                    $factory = $this->get('security.encoder_factory');
                    $encoder = $factory->getEncoder($user);
                    $password = $encoder->encodePassword($password, $user->getSalt());
                    $user->setPassword($password);

                    $em->persist($user);
                    $em->flush();

                    $userEvent = $this->get('security.context')->getToken()->getUser();
                    $sysEvent = new SysEvent();
                    $sysEvent->setUserId($userEvent->getId());
                    $sysEvent->setEvent(SysEvent::UPDATE_PASSWORD . " of user " . $userId);
                    $sysEvent->setIp($this->container->get('request')->getClientIp());
                    $eventTracker = $this->get('sys_event_tracker');
                    $eventTracker->track($sysEvent);

                    $logger->info('[CHANGE_PASSWORD] For user with id: ' . $userId);

                    $redirectUri = $this->generateUrl('customer_settings');

                    if (($this->get('security.context')->isGranted('ROLE_ADMIN'))) {
                        $redirectUri = $this->generateUrl('admin_customer_settings', array('userId' => $userId));
                    }
                } else {
                    $userNotFoundErrorMessage = "User not found!";
                    $errorList .= "<li>" . $userNotFoundErrorMessage . "</li>";
                    $redirectUri = $this->generateUrl('customer_settings', array("password_setting_errors" => $errorList));
                }

            } else {
                $errorList .= count($oldPasswordErrorList) > 0 ? "<li>" . $oldPasswordErrorList[0]->getMessage() . "</li>" : "";
                $errorList .= count($passwordErrorList) > 0 ? "<li>" . $passwordErrorList[0]->getMessage() . "</li>" : "";
                $errorList .= count($passwordRepeatErrorList) > 0 ? "<li>" . $passwordRepeatErrorList[0]->getMessage() . "</li>" : "";
                $errorList .= !$passwordsEqual ? "<li>" . "Пароли не равны!" . "</li>" : "";

                $errorList = "<ul>" . $errorList . "</ul>";

                $this->get('session')->getFlashBag()->add('password_setting_errors', $errorList);

                $redirectUri = $this->generateUrl('customer_settings');

                if (($this->get('security.context')->isGranted('ROLE_ADMIN'))) {
                    $redirectUri = $this->generateUrl('admin_customer_settings', array('userId' => $userId));
                }
            }
        }

        return $this->redirect($redirectUri);

    }
}