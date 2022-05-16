<?php

namespace App\Controller;


use Twig\Environment;
use App\Entity\Comment;
use App\Entity\Conference;
use App\Form\CommentFormType;
use App\Repository\CommentRepository;
use App\Repository\ConferenceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response; 
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException; 

class ConferenceController extends AbstractController
{
    private $twig;
    private $em;

    public function __construct(Environment $twig,EntityManagerInterface $em)
    {
        $this->twig = $twig;
        $this->em = $em;
    }
    /**
     * @Route("/", name="homepage")
     */
    public function index(ConferenceRepository $conferenceRepository): Response
    {
        return new Response($this->twig->render('conference/index.html.twig', [
            'conferences' => $conferenceRepository->findAll(),
        ]));
    }

    /**
    * @Route("/conference/{slug}", name="conference")
    */
    public function show(Request $request, 
    Conference $conference,
    CommentRepository $commentRepository,
    string $photoDir)
    {
        $comment = new Comment();
        $form = $this->createForm(CommentFormType::class, $comment);

        $form->handleRequest($request);
        if($form->isSubmitted() && $form->isValid()){
            $comment->setConference($conference);
            
            if ($photo = $form['photo']->getData()) {
                $filename = bin2hex(random_bytes(6)).'.'.$photo->guessExtension();
            try {
                $photo->move($photoDir, $filename);
            } catch (FileException $e) {
                // unable to upload the photo, give up
            }
                $comment->setPhotoFilename($filename);
            }


            $this->em->persist($comment);
            $this->em->flush();
            return $this->redirectToRoute('conference', ['slug' => $conference->getSlug()]); 
        }
        $offset = max(0, $request->query->getInt('offset', 0));
        $paginator = $commentRepository->getCommentPaginator($conference,$offset);

        return new Response($this->twig->render('conference/show.html.twig', [
        'conference' => $conference,
        'comments' => $paginator,
        'comment_form' => $form->createView(),
        'previous' => $offset - CommentRepository::PAGINATOR_PER_PAGE,
        'next' => min(count($paginator), $offset + CommentRepository::PAGINATOR_PER_PAGE),])); 
    }

}
