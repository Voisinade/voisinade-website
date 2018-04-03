<?php

namespace App\Controller;

use App\Entity\FuturFan;
use App\Entity\Subscriber;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Welp\MailchimpBundle\Event\SubscriberEvent;

class FrontController extends Controller
{
    /**
     * @Route("/", name="home")
     */
    public function index(Request $request)
    {
        $subscriber = new Subscriber();
        $form = $this->createFormBuilder($subscriber)
            ->add('email', EmailType::class)
            ->add('save', SubmitType::class, array('label' => 'Rejoindre le mouvement'))
            ->getForm();

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // $form->getData() holds the submitted values
            // but, the original `$task` variable has also been updated
            $subscriber = $form->getData();

            // ... perform some action, such as saving the task to the database
            // for example, if Task is a Doctrine entity, save it!
            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->persist($subscriber);
            $entityManager->flush();

            $subscriber = new \Welp\MailchimpBundle\Subscriber\Subscriber($subscriber->getEmail(), [], ['language' => 'fr']);
            $this->get('event_dispatcher')->dispatch(
                SubscriberEvent::EVENT_SUBSCRIBE,
                new SubscriberEvent(281, $subscriber)
            );
        }

        return $this->render('front/index.html.twig', [
            'form' => $form->createView(),
        ]);
    }
}
