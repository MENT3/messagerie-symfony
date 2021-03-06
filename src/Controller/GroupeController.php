<?php

namespace App\Controller;

use App\Entity\User;
use App\Entity\Groupe;
use App\Entity\Message;
use App\Form\GroupeType;
use App\Form\MessageType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class GroupeController extends AbstractController
{
    private $entityManager;

    public function __construct(EntityManagerInterface $entityManager) {
        $this->entityManager = $entityManager;
    }

    /**
     * @Route("/", name="default")
     */
    public function redirectToGroup() {

        if ($this -> getuser()) {
            $repo = $this -> getDoctrine() -> getRepository(User::class);
            $user = $this -> getUser();

            // Get lastest message id
            $grpsId = array(); 
            foreach ($user -> getMessages() as $msg) {
                array_push($grpsId, $msg -> getGroupe() -> getId());
            }

            // dd($grpsId);

            // If there is no message, get the last group
            if ($grpsId == []) {
                foreach ($user -> getGroupes() as $grp) {
                    array_push($grpsId, $grp -> getId());
                }
                if ($grpsId == []) {
                    $this -> addFlash('danger', 'Vous n\'avez aucun groupe, saisissez l\'occasion');
                    return $this -> redirectToRoute('new');
                } else {
                    $lastId = end($grpsId);
                }
            }

            $lastId = end($grpsId);

            // dd($user -> getGroupes());
            return $this -> redirectToRoute('groupe', array(
                'id' => $lastId
            ));
        } else {
            return $this -> redirectToRoute('login');
        }
    }

    /**
     * @Route("/groupe/{id}", name="groupe")
     */
    public function groupe(Request $req, int $id) {

        $repo = $this -> getDoctrine() -> getRepository(Groupe::class);
        // Get groupe
        $groupe = $repo -> find($id);

        if ($groupe != null) {
            
            // Get all messages
            $messages = $groupe -> getMessages();

            // Get user id
            $userId = $this -> getUser() -> getId();

            $repo = $this -> getDoctrine() -> getRepository(User::class);
            $groupes = $repo -> find($userId) -> getGroupes();

            $grpsId = [];
            foreach ($groupes as $grp) {
                array_push($grpsId, $grp -> getId());
            }


            if (in_array($id, $grpsId)) {
        
                // Message form
                $msg = new Message();
                $msg -> setUser($this -> getUser());
                $msg -> setDate(new \DateTime('now'));
                $msg -> setGroupe($groupe);
                $msg -> setState(1);
        
                // Create form
                $formMessage = $this -> createForm(MessageType::class, $msg);
        
                $formMessage -> handleRequest($req);
        
                if($formMessage -> isSubmitted() && $formMessage -> isValid()) {
        
                    // Persist on base
                    $this -> entityManager -> persist($msg);
                    $this -> entityManager -> flush();
        
                    return $this -> redirect($req -> getUri());
                }
        
                return $this -> render('groupe/index.html.twig', [
                    'access' => true,
                    'groupes' => $groupes,
                    'messages' => $messages,
                    'formMessage' => $formMessage -> createView(),
                ]);
            } 
        } 
        $this -> addFlash('danger', 'Vous n\'avez pas le droit d\'accéder à cette conversation');
        return $this -> render('groupe/index.html.twig', [
            'access' => false
        ]);
    }

    /**
     * @Route("/new", name="new")
     */
    public function new(Request $req) {

        $grp = new Groupe();

        $grp -> setDate(new \DateTime('now'));
        $grp -> setUserP($this -> getUser());
        $grp -> setImg('default.png');
            
        // Create form
        $form = $this -> createForm(GroupeType::class, $grp);
        
        $form -> handleRequest($req);
        
        if($form -> isSubmitted()) {
            $this -> entityManager -> persist($grp);

            // If no file set default img
            if ($grp -> getFile() != null) {
                $img = $grp -> getFile();

                $imgName = md5(uniqid()) . '.' . $img -> guessExtension();
                $img -> move($this -> getParameter('upload_directory'), $imgName);
                $grp -> setImg($imgName);
            } else {
                $grp -> setImg('default.png');
            }

            // Push on base
            $this -> entityManager -> flush();


            $this -> addFlash('success', 'Le groupe à été créé correctement');
            return $this -> redirectToRoute('default');
        }

        return $this -> render('groupe/new.html.twig',[
            'form' => $form -> createView()
        ]);
    }
}