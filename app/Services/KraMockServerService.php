<?php

namespace App\Services;

use SimpleXMLElement;
use Illuminate\Support\Facades\Log;

class KraMockServerService
{
    /**
     * Generate mock XML response for X_REPORT command
     * @param string $pin
     * @return string XML response
     */
    public function generateXReportResponse(string $pin): string
    {
        $simulationData = config('kra.simulation_responses.x_report');
        
        $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><KRA></KRA>');
        $xml->addChild('CMD', 'X_REPORT');
        $xml->addChild('PIN', $pin);
        
        $data = $xml->addChild('DATA');
        $data->addChild('tradeName', $simulationData['tradeName']);
        $data->addChild('PIN', $simulationData['PIN']);
        $data->addChild('date', $simulationData['date']);
        $data->addChild('time', $simulationData['time']);
        $data->addChild('reportType', $simulationData['reportType']);
        $data->addChild('totalSalesAmountNS', $simulationData['totalSalesAmountNS']);
        $data->addChild('numberOfSalesReceiptsNS', $simulationData['numberOfSalesReceiptsNS']);
        $data->addChild('totalCreditNoteAmountNC', $simulationData['totalCreditNoteAmountNC']);
        $data->addChild('numberOfCreditNotesNC', $simulationData['numberOfCreditNotesNC']);
        $data->addChild('taxableAmountA', $simulationData['taxableAmountA']);
        $data->addChild('taxableAmountB', $simulationData['taxableAmountB']);
        $data->addChild('taxAmountA', $simulationData['taxAmountA']);
        $data->addChild('taxAmountB', $simulationData['taxAmountB']);
        $data->addChild('openingDeposit', $simulationData['openingDeposit']);
        $data->addChild('numberOfItemsSold', $simulationData['numberOfItemsSold']);
        $data->addChild('numberOfReceiptCopies', $simulationData['numberOfReceiptCopies']);
        $data->addChild('totalReceiptCopiesAmount', $simulationData['totalReceiptCopiesAmount']);
        $data->addChild('numberOfTrainingReceipts', $simulationData['numberOfTrainingReceipts']);
        $data->addChild('totalTrainingReceiptsAmount', $simulationData['totalTrainingReceiptsAmount']);
        $data->addChild('numberOfProformaReceipts', $simulationData['numberOfProformaReceipts']);
        $data->addChild('totalProformaReceiptsAmount', $simulationData['totalProformaReceiptsAmount']);
        $data->addChild('allDiscounts', $simulationData['allDiscounts']);
        $data->addChild('otherReductionsAmount', $simulationData['otherReductionsAmount']);
        $data->addChild('numberOfIncompleteSales', $simulationData['numberOfIncompleteSales']);
        
        return $xml->asXML();
    }

    /**
     * Generate mock XML response for Z_REPORT command
     * @param string $pin
     * @return string XML response
     */
    public function generateZReportResponse(string $pin): string
    {
        $simulationData = config('kra.simulation_responses.z_report');
        
        $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><KRA></KRA>');
        $xml->addChild('CMD', 'Z_REPORT');
        $xml->addChild('PIN', $pin);
        
        $data = $xml->addChild('DATA');
        $data->addChild('tradeName', $simulationData['tradeName']);
        $data->addChild('PIN', $simulationData['PIN']);
        $data->addChild('date', $simulationData['date']);
        $data->addChild('time', $simulationData['time']);
        $data->addChild('reportType', $simulationData['reportType']);
        $data->addChild('totalSalesAmountNS', $simulationData['totalSalesAmountNS']);
        $data->addChild('numberOfSalesReceiptsNS', $simulationData['numberOfSalesReceiptsNS']);
        $data->addChild('totalCreditNoteAmountNC', $simulationData['totalCreditNoteAmountNC']);
        $data->addChild('numberOfCreditNotesNC', $simulationData['numberOfCreditNotesNC']);
        $data->addChild('taxableAmountA', $simulationData['taxableAmountA']);
        $data->addChild('taxableAmountB', $simulationData['taxableAmountB']);
        $data->addChild('taxAmountA', $simulationData['taxAmountA']);
        $data->addChild('taxAmountB', $simulationData['taxAmountB']);
        $data->addChild('openingDeposit', $simulationData['openingDeposit']);
        $data->addChild('numberOfItemsSold', $simulationData['numberOfItemsSold']);
        $data->addChild('numberOfReceiptCopies', $simulationData['numberOfReceiptCopies']);
        $data->addChild('totalReceiptCopiesAmount', $simulationData['totalReceiptCopiesAmount']);
        $data->addChild('numberOfTrainingReceipts', $simulationData['numberOfTrainingReceipts']);
        $data->addChild('totalTrainingReceiptsAmount', $simulationData['totalTrainingReceiptsAmount']);
        $data->addChild('numberOfProformaReceipts', $simulationData['numberOfProformaReceipts']);
        $data->addChild('totalProformaReceiptsAmount', $simulationData['totalProformaReceiptsAmount']);
        $data->addChild('allDiscounts', $simulationData['allDiscounts']);
        $data->addChild('otherReductionsAmount', $simulationData['otherReductionsAmount']);
        $data->addChild('numberOfIncompleteSales', $simulationData['numberOfIncompleteSales']);
        
        return $xml->asXML();
    }

    /**
     * Generate mock XML response for PLU_REPORT command
     * @param string $pin
     * @param string|null $startDate
     * @param string|null $endDate
     * @return string XML response
     */
    public function generatePLUReportResponse(string $pin, ?string $startDate = null, ?string $endDate = null): string
    {
        $simulationData = config('kra.simulation_responses.plu_report');
        
        $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><KRA></KRA>');
        $xml->addChild('CMD', 'PLU_REPORT');
        $xml->addChild('PIN', $pin);
        
        $data = $xml->addChild('DATA');
        $data->addChild('companyName', $simulationData['companyName']);
        $data->addChild('taxIdentificationNumber', $simulationData['taxIdentificationNumber']);
        $data->addChild('intervalDate', $simulationData['intervalDate']);
        $data->addChild('intervalTime', $simulationData['intervalTime']);
        $data->addChild('reportType', $simulationData['reportType']);
        
        // Add items
        $items = $data->addChild('items');
        foreach ($simulationData['items'] as $itemData) {
            $item = $items->addChild('item');
            $item->addChild('itemCode', $itemData['itemCode']);
            $item->addChild('itemName', $itemData['itemName']);
            $item->addChild('unitPrice', $itemData['unitPrice']);
            $item->addChild('taxRate', $itemData['taxRate']);
            $item->addChild('quantitySold', $itemData['quantitySold']);
            $item->addChild('amountCollected', $itemData['amountCollected']);
            $item->addChild('remainQuantityInStock', $itemData['remainQuantityInStock']);
        }
        
        return $xml->asXML();
    }

    /**
     * Process KRA command and return appropriate mock response
     * @param string $command
     * @param string $pin
     * @param array $dataPayload
     * @return string XML response
     */
    public function processCommand(string $command, string $pin, array $dataPayload = []): string
    {
        Log::info("Mock server processing command: {$command}", [
            'pin' => $pin,
            'dataPayload' => $dataPayload
        ]);

        return match (strtoupper($command)) {
            'X_REPORT' => $this->generateXReportResponse($pin),
            'Z_REPORT' => $this->generateZReportResponse($pin),
            'PLU_REPORT' => $this->generatePLUReportResponse(
                $pin, 
                $dataPayload['startDate'] ?? null, 
                $dataPayload['endDate'] ?? null
            ),
            default => $this->generateErrorResponse($command, 'Unknown command'),
        };
    }

    /**
     * Generate error response
     * @param string $command
     * @param string $errorMessage
     * @return string XML response
     */
    private function generateErrorResponse(string $command, string $errorMessage): string
    {
        $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><KRA></KRA>');
        $xml->addChild('CMD', $command);
        $xml->addChild('ERROR', $errorMessage);
        
        return $xml->asXML();
    }
} 