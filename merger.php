<?php
/**
 * XML Feed Merger - Sellar Imóveis
 */

set_time_limit(0); 
ini_set('memory_limit', '512M');

// 1. CONFIGURAÇÃO DOS SEUS FEEDS REAIS
$feeds = [
    'VISTA'   => 'https://sellarad-portais.vistahost.com.br/c14792e618901803a97153fd732ae2b7',
    'Caixa.Sellar' => 'https://caixa.sellarimoveis.com.br/imoveis/72140b938c5d66ba34cf2805/xml',
    'NONSTOP' => 'https://www.usenonstop.com/integracoes/zap/sellarimoveis',
];

// Nome do arquivo que você vai enviar para o VivaReal/ZAP
$outputFile = 'feed_unificado_sellar.xml';

try {
    $isBrowser = isset($_SERVER['HTTP_USER_AGENT']);
    if ($isBrowser) echo "<pre style='background:#222; color:#0f0; padding:20px; font-family:monospace;'>";

    echo "Iniciando Unificação Sellar Imóveis..." . PHP_EOL;

    $writer = new XMLWriter();
    $writer->openUri($outputFile);
    $writer->startDocument('1.0', 'UTF-8');
    $writer->setIndent(true);

    // Schema VrSync
    $writer->startElement('ListingDataFeed');
    $writer->writeAttribute('xmlns', 'http://www.vivareal.com/schemas/1.0/VRSync');
    $writer->writeAttribute('xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance');
    $writer->writeAttribute('xsi:schemaLocation', 'http://www.vivareal.com/schemas/1.0/VRSync http://xml.vivareal.com/vrsync.xsd');

    // Cabeçalho da Sellar Imóveis
    $writer->startElement('Header');
        $writer->writeElement('Provider', 'Sellar Imoveis Middleware');
        $writer->writeElement('Email', 'contato@sellarimoveis.com.br'); // Ajuste se necessário
        $writer->writeElement('Date', date('Y-m-d\TH:i:s'));
    $writer->endElement(); 

    $writer->startElement('Listings');

    $dom = new DOMDocument();
    $totalProcessed = 0;

    foreach ($feeds as $prefix => $feedUrl) {
        echo "Lendo fonte: {$prefix}... ";
        
        $reader = new XMLReader();
        
        // Tentativa de abrir a URL
        if (!$reader->open($feedUrl)) {
            echo "[ERRO: Não foi possível acessar esta URL]" . PHP_EOL;
            continue;
        }

        while ($reader->read()) {
            if ($reader->nodeType === XMLReader::ELEMENT && $reader->localName === 'Listing') {
                $node = $reader->expand($dom);
                
                // RESOLUÇÃO DE CONFLITO: Adiciona Prefixo ao ID do imóvel
                $idTags = $node->getElementsByTagName('ListingID');
                if ($idTags->length > 0) {
                    $originalId = $idTags->item(0)->nodeValue;
                    $idTags->item(0)->nodeValue = $prefix . '-' . $originalId;
                }

                $xmlString = $dom->saveXML($node);
                $writer->writeRaw($xmlString);
                $totalProcessed++;
            }
        }
        $reader->close();
        echo "[OK]" . PHP_EOL;
    }

    $writer->endElement(); // Listings
    $writer->endElement(); // ListingDataFeed
    $writer->endDocument();
    $writer->flush();

    echo PHP_EOL . "------------------------------------------------" . PHP_EOL;
    echo "SUCESSO! Feed unificado com {$totalProcessed} imóveis." . PHP_EOL;
    echo "Link para o portal: " . dirname((isset($_SERVER['HTTPS']) ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]") . "/" . $outputFile . PHP_EOL;
    
    if ($isBrowser) echo "</pre>";

} catch (Exception $e) {
    echo "ERRO CRÍTICO: " . $e->getMessage() . PHP_EOL;
    exit(1);
}
?>
