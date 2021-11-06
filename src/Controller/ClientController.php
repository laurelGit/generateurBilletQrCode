<?php

namespace App\Controller;

use App\Entity\Client;
use App\Form\ClientFormType;
use App\Form\ClientFormVerifType;
use App\Form\CustomPayType;
use App\Repository\ClientRepository;
use App\Services\CenterService;
use App\Services\Download;
use App\Services\QrcodeService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Zxing\QrReader;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

/**
 * @Route("/client", name="client.")
 */
class ClientController extends AbstractController
{

    
    /**
     * @Route("/", name="index")
     * @param $clientRepository $clientRepository
     * 
     * @return Response
     */
    public function index(ClientRepository $clientRepository): Response
    {
        
        $clients = $clientRepository->findAll();
        // dump($clients);die;
        // foreach($clients as $key=>$value){
        //     $value->setQrcode(base64_encode(stream_get_contents($value->getQrcode())));
        // }
        return $this->render('client/index.html.twig', [
            'clients' => $clients
        ]);
    }


    /**
     * @Route("/creerClient", name="creerClient")
     * @param Request $request
     * @param QrcodeService $qrcodeService
     * @return Response
     */
    public function creerClient(Request $request, QrcodeService $qrcodeService)
    {

        // dd($qrcodeService->qrcode('wilfried'));
        
        //creer nouveau client
        $client = new Client();

        //creer formulaire avec ClientFormType
        $form = $this->createForm(ClientFormType::class, $client);
        $form->handleRequest($request);

        // verification formulaire
        if ($form->isSubmitted()) {
            // manager
            $em = $this->getDoctrine()->getManager();
            $data = $client->getNom()."&".$client->getContact()."&".$client->getEmail();

            $qrcode = $qrcodeService->qrcode($data);

            $client->setQrcode($qrcode);
            // dump($client);die;
            $em->persist($client);
            $em->flush();

            return $this->redirect($this->generateUrl('client.gen_billet', [
                'qrcode' => $client->getQrcode()
            ]));
        }

        return $this->render('client/show.html.twig', [
            'form' => $form->createView()
        ]);
    }

    /**
     * @Route("/decode/{qrcode}", name="decode")
     * @param Client $client
     * @param Request $request
     * @return Response
     */
    public function decode(Request $request, Client $client){

        $namePng = $request->get('qrcode');
        // dump($namePng);die;

        $path = dirname(__DIR__, 2).'/public/assets/';
        $imgpath = $path.'qr-code/'.$namePng;

        $qrcode = new QrReader($imgpath);
        $text = $qrcode->text();
        $textArray = $this->split_qr($text);
        $contact = $textArray[1];
        $email = $textArray[2];
        $nom = $textArray[0];

        return $this->render('client/decode.html.twig', [
            'client' => $client,
            'contact' => $contact,
            'email' => $email,
            'nom' => $nom
        ]);
    }

    private function split_qr($data){
        $textArray = explode("&", $data);
        return $textArray;
    }

    /**
     * @Route("/gen_billet/{qrcode}", name="gen_billet")
     * @param Client $client
     * @param Request $request
     * @param CenterService $centerService
     * @param Download $download
     * @return Response
     */
    public function genererBillet(Request $request, Client $client, CenterService $centerService, Download $download){
        $namePng = $request->get('qrcode');
        // dump($namePng);die;

        $path = dirname(__DIR__, 2).'/public/assets/';
        $imgpath = $path.'qr-code/'.$namePng;
        
        $imgName = $centerService->makeCenter($imgpath, $client);
        return $this->render('client/genererBillet.html.twig', [
            'imgName' => $imgName,
            'client' => $client
        ]);
    }

    /**
     * @Route("/download_img/{imgName}", name="download_img")
     * @param Request $request
     * @param Download $download
     * @return Response
     */
    public function downloadImg(Request $request, Download $download){
        $imgName = $request->get('imgName');
        $path = dirname(__DIR__, 2).'/public/assets/';
        $imgPath = $path.'img_billet/'.$imgName;
        $download->load($imgPath);
        return new Response('<h4>File downloaded</h4>');
    }


    /**
     * @Route("/scan_decoder/{contentData?}", name="scan_decoder")
     * @param Request $request
     * @param ClientRepository $clientRepository
     * @return Response
     */
    public function scanDecoder(Request $request, ClientRepository $clientRepository){
        $data = $request->get('contentData');
        dump($data);
        $error = null;
        if($data != null){
            $split_data = $this->split_qr($data);
            $client = $clientRepository->findOneBy(['email' => $split_data[2]]);

            return $this->redirect($this->generateUrl('client.scan_results', [
                'email' => $client->getEmail()
            ]));
        }
        $error = "Billet non trouver";
        return $this->render('client/scanDecoder.html.twig', [
            'error' => $error
        ]);
    }

    /**
     * @Route("/scan_results/{email}", name="scan_results")
     * @param Request $request
     * @param Client $client
     * @return Response
     */
    public function scanResults(Client $client, Request $request){
        $status = "Payer";
        $verifier = "Verifier";
        $nClient = new Client;
        $form = $this->createForm(CustomPayType::class, $nClient);
        $formVerif = $this->createForm(ClientFormVerifType::class, $nClient);
        $form->handleRequest($request);
        $formVerif->handleRequest($request);
        dump($client);
        
        if ($form->isSubmitted() && $form->isValid()) {
            // manager
            $em = $this->getDoctrine()->getManager();
            // $product = $entityManager->getRepository(Product::class)->find($id);
            $nClient = $em->getRepository(Client::class)->findOneBy([
                'email' => $client->getEmail(),
            ]);
            dump($nClient);
            if (!$nClient) {
                throw $this->createNotFoundException(
                    'No data found for email '.$client->getEmail()
                );
            }

            // $product->setName('New product name!');
            $nClient->setPayer($client->getEmail());
            // $form->getData();
            // dump($post);
            // $em->persist($post);
            $em->flush();
        }else if ($formVerif->isSubmitted() && $formVerif->isValid()) {
            // manager
            $em = $this->getDoctrine()->getManager();
            // $product = $entityManager->getRepository(Product::class)->find($id);
            $nClient = $em->getRepository(Client::class)->findOneBy([
                'email' => $client->getEmail()
            ]);
            
            dump($nClient);
            if (!$nClient) {
                throw $this->createNotFoundException(
                    'No data found for email '.$client->getEmail()
                );
            }
            $nClient->setVerifier($client->getEmail());
            $em->flush();
        }
        
        if($client->getPayer() == null && $client->getVerifier() == null){
            $status = "Non Payer";
            $verifier = "Non Verifier";

            return $this->render('client/scanResults.html.twig', [
                'client' => $client,
                'status' => $status,
                'form' => $form->createView()
            ]);
        }else if($client->getPayer() != null && $client->getVerifier() == null){
            dump($client->getVerifier());
            $status = "Payer";
            $verifier = "Non Verifier";
            return $this->render('client/scanResults.html.twig', [
                'client' => $client,
                'status' => $status,
                'verifier' => $verifier,
                'formv' => $formVerif->createView()
            ]);
        }else if($client->getPayer() != null && $client->getVerifier() != null){
            $status = "Payer";
            $verifier = "Verifier";
        }

        
        return $this->render('client/scanResults.html.twig', [
            'client' => $client,
            'status' => $status,
            'verifier' => $verifier,
            'form' => $form->createView(),
            'formv' => $formVerif->createView()
        ]);
    }
}
