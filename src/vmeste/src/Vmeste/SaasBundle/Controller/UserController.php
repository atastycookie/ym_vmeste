<?php
/**
 * Created by PhpStorm.
 * Authors: Eugene Avrukevich <eugene.avrukevich@gmail.com>
 * Date: 9/18/14
 * Time: 9:42 PM
 */

namespace Vmeste\SaasBundle\Controller;


use Doctrine\ORM\Tools\Pagination\Paginator;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Vmeste\SaasBundle\Entity\User;

class UserController extends Controller
{

    /**
     * @Template
     */
    public function homeAction()
    {

        $userSuccessfullyCreatedMessage = null;
        if($this->getRequest()->query->get("user_creation") == 'success')
            $userSuccessfullyCreatedMessage = "New user has been created successfully!";


        $limit = 2;
        $pageOnSidesLimit = 2;

        $page = $this->getRequest()->query->get("page", 1);

        $em = $this->getDoctrine()->getManager();
        $queryBuilder = $em->createQueryBuilder();

        $queryBuilder->select('u')->from('Vmeste\SaasBundle\Entity\User', 'u');

        $queryBuilder->setFirstResult(($page - 1) * $limit)->setMaxResults($limit);

        $paginator = new Paginator($queryBuilder, $fetchJoinCollection = true);

        $totalItems = count($paginator);

        $pageCount = (int) ceil($totalItems / $limit);

        $pageNumberArray = array();

        if ($page > $pageOnSidesLimit + 1) {
            for ($i = $page - $pageOnSidesLimit; $i < $page; $i++) {
                array_push($pageNumberArray, $i);
            }
        } else {
            for ($i = 1; $i < $page; $i++) {
                array_push($pageNumberArray, $i);
            }
        }

        array_push($pageNumberArray, $page);

        if ($page + $pageOnSidesLimit < $pageCount) {
            for ($i = $page + 1; $i <= $page + $pageOnSidesLimit; $i++) {
                array_push($pageNumberArray, $i);
            }
        } else {
            for ($i = $page + 1; $i <= $pageCount; $i++) {
                array_push($pageNumberArray, $i);
            }
        }

        return array('users' => $paginator, 'pages' => $pageNumberArray, 'page' => $page, 'user_created' => $userSuccessfullyCreatedMessage);
    }

    /**
     * @Template
     */
    public function createAction(Request $request)
    {

        $currentUser = $this->get('security.context')->getToken()->getUser();


        $form = $this->createFormBuilder()
            ->add('email', 'text', array('constraints' => array(
                new NotBlank(),
                new Email(),
            )))
            ->add('username', 'text', array('constraints' => array(
                new NotBlank(),
                new Length(array('min' => 3, 'max' => 25)),
            )))
            ->add('password', 'repeated', array(
                'type' => 'password',
                'invalid_message' => 'The password fields must match.',
                'options' => array('attr' => array('class' => 'password-field')),
                'required' => true,
                'first_options' => array('label' => 'Password'),
                'second_options' => array('label' => 'Repeat Password'),
                'constraints' => array(
                    new NotBlank(),
                    new Length(array('min' => 6, 'max' => 64)),
                )))
            ->add('role', 'choice', array(
                'choices' => array(
                    'ROLE_USER' => 'User',
                    'ROLE_ADMIN' => 'Administrator',
                ),
                'label' => 'Role',
                'constraints' => array(
                    new Choice(array(
                            'choices' => array('ROLE_USER', 'ROLE_ADMIN'),
                            'message' => 'Choose a valid role.',
                        )
                )
            )))
            ->add('save', 'submit', array('label' => 'Create User'))
            ->getForm();

        $form->handleRequest($request);

        if ($form->isValid()) {

            $data = $form->getData();

            $em = $this->getDoctrine()->getManager();

            $user = new User();
            $user->setUsername($data['username']);
            $user->setEmail($data['email']);
            $user->setPassword($data['password']);
            $role = $em->getRepository('Vmeste\SaasBundle\Entity\Role')->findOneBy(array('role' => $data['role']));
            $user->addRole($role);
            $role->addUser($user);
            $status = $em->getRepository('Vmeste\SaasBundle\Entity\Status')->findOneBy(array('status' => 'ACTIVE'));
            $user->addStatus($status);
            $status->addUser($user);
            $user->setCreatedBy($currentUser->getId());

            $em->persist($user);
            $em->flush();

            return $this->redirect($this->generateUrl('admin_user', array('user_creation' => 'success')));
        }


        return array(
            'form' => $form->createView(),
        );
    }

    /**
     * @Template
     */
    public function editAction(Request $request)
    {

        $em = $this->getDoctrine()->getManager();

        $id = $this->getRequest()->query->get("id");

        $user = $em->getRepository('Vmeste\SaasBundle\Entity\User')->findOneBy(array('id' => $id));

        $form = $this->createFormBuilder()
            ->add('email', 'text', array('constraints' => array(
                new NotBlank(),
                new Email(),
            ), 'data' => $user->getEmail()))
            ->add('username', 'text', array('constraints' => array(
                new NotBlank(),
                new Length(array('min' => 3, 'max' => 25)),
            ),  'data' => $user->getUsername()))
            ->add('password', 'repeated', array(
                'type' => 'password',
                'invalid_message' => 'The password fields must match.',
                'options' => array('attr' => array('class' => 'password-field')),
                'required' => false,
                'first_options' => array('label' => 'Password'),
                'second_options' => array('label' => 'Repeat Password'),
                'constraints' => array(
                    new Length(array('min' => 6, 'max' => 64)),
                )))
            ->add('role', 'choice', array(
                'choices' => array(
                    'ROLE_USER' => 'User',
                    'ROLE_ADMIN' => 'Administrator',
                ),
                'data' => $user->getRole(),
                'label' => 'Role',
                'constraints' => array(
                    new Choice(array(
                            'choices' => array('ROLE_USER', 'ROLE_ADMIN'),
                            'message' => 'Choose a valid role.',
                        )
                    )
                )))
            ->add('save', 'submit', array('label' => 'Update User'))
            ->getForm();

        $form->handleRequest($request);
        if ($form->isValid()) {

            $data = $form->getData();

            $user->setUsername($data['username']);
            $user->setEmail($data['email']);
            if($data['password'] != NULL) {
                $user->setPassword($data['password']);
            }

            if($data['role'] != $user->getRole()) {

                $currentRole = $em->getRepository('Vmeste\SaasBundle\Entity\Role')->findOneBy(array('role' => $user->getRole()));
                $user->removeRole($currentRole);
                $currentRole->removeUser($user);

                $newRole = $em->getRepository('Vmeste\SaasBundle\Entity\Role')->findOneBy(array('role' => $data['role']));
                $user->addRole($newRole);
                $newRole->addUser($user);
            }

            $em->persist($user);
            $em->flush();

            return $this->redirect($this->generateUrl('admin_user', array('user_creation' => 'success')));
        }


        //return $this->redirect($this->generateUrl('admin_user', array('user_modifying' => 'success')));

        return array(
            'form' => $form->createView(),
        );

    }

    public function blockAction()
    {

        $id = $this->getRequest()->query->get("id");
        $page = $this->getRequest()->query->get("page");

        $em = $this->getDoctrine()->getManager();
        $user = $em->getRepository('Vmeste\SaasBundle\Entity\User')->findOneBy(array('id' => $id));

        $statuses = $user->getStatuses();
        $currentStatus = $statuses[0];

        $user->removeStatus($currentStatus);

        $statusBlocked = $em->getRepository('Vmeste\SaasBundle\Entity\Status')->findOneBy(array('status' => 'BLOCKED'));
        $user->addStatus($statusBlocked);

        $em->persist($user);
        $em->flush();

        return $this->redirect($this->generateUrl('admin_user', array('page' => $page)));
    }

    public function activateAction()
    {

        $id = $this->getRequest()->query->get("id");
        $page = $this->getRequest()->query->get("page");

        $em = $this->getDoctrine()->getManager();
        $user = $em->getRepository('Vmeste\SaasBundle\Entity\User')->findOneBy(array('id' => $id));

        $statuses = $user->getStatuses();
        $currentStatus = $statuses[0];

        $user->removeStatus($currentStatus);

        $statusActivated = $em->getRepository('Vmeste\SaasBundle\Entity\Status')->findOneBy(array('status' => 'ACTIVE'));
        $user->addStatus($statusActivated);

        $em->persist($user);
        $em->flush();

        return $this->redirect($this->generateUrl('admin_user', array('page' => $page)));
    }

    /**
     * @Template
     */
    public function deleteAction()
    {


        return $this->redirect($this->generateUrl('admin_user', array('page' => $page)));
    }

} 