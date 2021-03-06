<?php

namespace Cekurte\ZCPEBundle\Form\Handler;

use Cekurte\ZCPEBundle\Entity\Answer;
use Cekurte\ZCPEBundle\Entity\QuestionHasAnswer;

use Symfony\Component\Security\Core\SecurityContextInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Form\FormInterface;
use Doctrine\ORM\EntityManager;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBag;
use FOS\UserBundle\Model\UserInterface;

/**
 * Question handler.
 *
 * @author João Paulo Cercal <sistemas@cekurte.com>
 * @version 0.1
 */
class QuestionFormHandler extends CustomFormHandler
{
    /**
     * @var SecurityContextInterface
     */
    protected $securityContext;

    /**
     * QuestionFormHandler
     *
     * @param FormInterface             $form
     * @param Request                   $request
     * @param EntityManager             $em
     * @param FlashBag                  $flashBag
     * @param UserInterface             $user
     * @param SecurityContextInterface  $securityContext
     */
    public function __construct(FormInterface $form, Request $request, EntityManager $em, FlashBag $flashBag, UserInterface $user, SecurityContextInterface $securityContext)
    {
        parent::__construct($form, $request, $em, $flashBag, $user);

        $this->setSecurityContext($securityContext);
    }

    /**
     * Gets the security.context di.
     *
     * @return SecurityContextInterface
     */
    public function getSecurityContext()
    {
        return $this->securityContext;
    }

    /**
     * Sets the securityContext.
     *
     * @param SecurityContextInterface $securityContext the security context
     *
     * @return QuestionFormHandler
     */
    public function setSecurityContext(SecurityContextInterface $securityContext)
    {
        $this->securityContext = $securityContext;

        return $this;
    }

    /**
     * {@inherited}
     */
    public function save()
    {
        $answers = array_filter($this->getRequest()->get('option_answers',  array()));
        $correct = array_filter($this->getRequest()->get('correct_answers', array()));

        if (empty($answers)) {

            $this->getFlashBag()->add('message', array(
                'type'      => 'error',
                'message'   => 'Set the answers!',
            ));

            return false;
        }

        try {

            $this->getManager()->getConnection()->beginTransaction();

            $data = $this->getForm()->getData();

            $questionId = $data->getId();

            if (!empty($questionId)) {

                $results = $this->getManager()->getRepository('CekurteZCPEBundle:QuestionHasAnswer')->findBy(array(
                    'question' => $data->getId(),
                ));

                foreach ($results as $key => $result) {
                    $this->getManager()->remove($result);
                }
            }

            $id = parent::save();

            foreach ($answers as $index => $answer) {

                $answerEntity = new Answer();

                $answerEntity
                    ->setTitle($answer)
                    ->setDeleted(false)
                    ->setCreatedBy($this->getUser())
                    ->setCreatedAt(new \DateTime('NOW'))
                ;

                $this->getManager()->persist($answerEntity);
                $this->getManager()->flush();

                $correctAnswer = false;

                foreach ($correct as $key => $indexCorrect) {
                    if ($indexCorrect == $index) {
                        $correctAnswer = true;
                    }
                }

                $questionAnswerEntity = new QuestionHasAnswer();

                $questionAnswerEntity
                    ->setAnswer($answerEntity)
                    ->setQuestion($data)
                    ->setCorrect($correctAnswer)
                ;

                $this->getManager()->persist($questionAnswerEntity);
                $this->getManager()->flush();
            }

            $question = $this->getManager()->getRepository('CekurteZCPEBundle:Question')->find($id);

            $question->setApproved(
                $this->getSecurityContext()->isGranted('ROLE_ADMIN')
            );

            $this->getManager()->persist($question);

            $this->getManager()->flush();

            $this->getManager()->getConnection()->commit();

            return $id;

        } catch (\Exception $e) {

            $this->getFlashBag()->clear();

            $this->getFlashBag()->add('message', array(
                'type'      => 'error',
                'message'   => $e->getMessage(),
            ));

            $this->getManager()->getConnection()->rollback();

            return false;
        }

        return false;
    }
}
