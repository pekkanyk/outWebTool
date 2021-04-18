<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use App\Entity\OutletTuote;
use Doctrine\ORM\EntityManagerInterface;


class ReloadService{
    private $client;
 
    public function __construct(HttpClientInterface $client)
    {
        $this->client = $client;
    }
    
    private function reloadProducts()
    {
        
        $jsonStr = $this->getJsonFromVk(0);
        $json = json_decode($jsonStr,true);
        $numPages = $json['numPages'];
        $outProducts = [];
        for ($i=0;$i<$numPages;$i++){
            $jsonStr = $this->getJsonFromVk($i);
            $json = json_decode($jsonStr,true);
            $vkProducts = $json['products'];
            
            for ($j=0;$j<count($vkProducts);$j++){
                $outletTuote = $this->generateOutletTuoteFromTable($vkProducts[$j]);
                array_push($outProducts,$outletTuote);
            }
        }
        
        //return $this->redirectToRou1te('homepage');
        //return new Response(print_r($vkProducts[0]["customerReturnsInfo"]));
        //return new Response(print_r($outProducts));
        return $outProducts;
    }
    
    public function updateDb(EntityManagerInterface $entityManager){
        $outProducts = $this->reloadProducts();
        $entityManager->persist($outProducts[1]);
        $entityManager->flush();
    }
    
    private function getJsonFromVk($i):string
    {
        $response = $this->client->request(
                'GET',
                'https://web-api.service.verkkokauppa.com/search?context=customer_returns_page&pageNo='.$i
        );
        return $response->getContent();
    }
    
    private function generateOutletTuoteFromTable($vkProduct): OutletTuote
    {
        $outletTuote = new OutletTuote();
        $outletTuote->setOutId($vkProduct["customerReturnsInfo"]["id"]);
        $outletTuote->setPid($vkProduct["customerReturnsInfo"]["pid"]);
        $outletTuote->setName($vkProduct["customerReturnsInfo"]["product_name"]);
        $outletTuote->setOutPrice($vkProduct["customerReturnsInfo"]["price_with_tax"]);
        $outletTuote->setPoistotuote($vkProduct["active"]);
        if ($vkProduct["active"] == 1){
            $outletTuote->setNorPrice($vkProduct["price"]["current"]);
        }
        else{
            $outletTuote->setNorPrice($vkProduct["price"]["original"]);
        }
        $outletTuote->setUpdated(false);
        if (array_key_exists("isFireSale", $vkProduct)){
            $outletTuote->setDumppituote($vkProduct["isFireSale"]);
        }
        else{
            $outletTuote->setDumppituote(false);
        }
        $outletTuote->setWarranty($vkProduct["customerReturnsInfo"]["warranty"]);
        $outletTuote->setCondition($vkProduct["customerReturnsInfo"]["condition"]);
        $outletTuote->setDeleted(null);
        $outletTuote->setFirstSeen($this->strDateToDate("today"));
        $outletTuote->setPidLuotu($this->strDateToDate($vkProduct["createdAt"]));
        $outletTuote->setPriceUpdatedDate($this->strDateToDate("today"));
        $alennus = 100.0*(1-($outletTuote->getOutPrice()/$outletTuote->getNorPrice()));
        $outletTuote->setAlennus($alennus);
        $outletTuote->setKampanja(false);
        $outletTuote->setKamploppuu(null);
        if (array_key_exists("discount", $vkProduct["price"])){
            if ($vkProduct["price"]["discount"]!=null){
                if ($vkProduct["price"]["discountAmount"]>0){
                    $outletTuote->setKampanja(true);
                    if ($vkProduct["price"]["discount"]["endAt"]!=null){
                        $outletTuote->setKamploppuu($this->strDateToDate($vkProduct["price"]["discount"]["endAt"]));
                    }
                    
                }
                
            }
            
        }
        if ($vkProduct["availability"]!=null){
            $outletTuote->setOnVarasto($vkProduct["availability"]["isPurchasable"]);
            if ($vkProduct["availability"]["hasStock"]){
                if(array_key_exists("web", $vkProduct["availability"]["stocks"])){
                    $outletTuote->setVarastossa($vkProduct["availability"]["stocks"]["web"]["stock"]);
                }
                else{
                    $outletTuote->setVarastossa(0);
                }
            }
        }
        else{
            $outletTuote->setVarastossa(null);
            $outletTuote->setOnVarasto(false);
        }
        $outletTuote->setKoko("K"); //TODO pid koon mukaan

        return $outletTuote;
    }
    
    private function strDateToDate(string $str)
    {
        if($str == 'today'){
            $str = "now";
        }
        else{
            $str = substr($str, 0, 10);
        }
            $date = date_create($str, new \DateTimeZone('Europe/Helsinki'));
        
        return $date;
    }
}