<?php

namespace Beycan\TronWeb3;

use Exception;

final class Transaction
{
    /**
     * Connection
     * @var Connection
     */
    private $connection;
    
    /**
     * Transaction id
     * @var string
     */
    private $id;

    /**
     * Transaction data
     * @var object
     */
    private $data;

    /**
     * @param string $id
     * @throws Exception
     */
    public function __construct(string $id)
    {
        if (!$this->connection = Connection::getConnection()) {
            throw new Exception("Please create a connection first!");
        }

        $this->id = $id;

        $this->data = $this->getData();
    }

    /**
     * @return string
     */
    public function getId() : string
    {
        return $this->id;
    }

    /**
     * @return object|null
     */
    public function getData() : ?object
    {
        $data = $this->connection->getTransaction($this->getId());
        $data->info = $this->connection->getTransactionInfo($this->getId());
        return $this->data = $data;
    }

    /**
     * @return object|null
     */
    public function decodeInput() : ?object
    {
        $input = $this->data->raw_data->contract[0]->parameter->value->data;

        $pattern = '/.+?(?=000000000000000000000000)/';
        preg_match($pattern, $input, $matches, PREG_OFFSET_CAPTURE, 0);
        $method = $matches[0][0];

        if ($input != '0x') {
            $input = str_replace($method, '', $input);
            $receiver = '41' . substr(substr($input, 0, 64), 24);
            $receiver = $this->connection->tron->fromHex($receiver);
            $amount = '0x' . ltrim(substr($input, 64), 0);
            return (object) compact('receiver', 'amount');
        } else {
            return null;
        }
    }

    /**
     * @return string
     */
    public function verify() : string
    {
        if (!isset($this->data->info->blockNumber)) {
            return 'pending';
        } else {
            if ($this->data->ret[0]->contractRet == 'REVERT') {
                return 'failed';
            } elseif (isset($this->data->info->result) && $this->data->info->result == 'FAILED') {
                return 'failed';
            } else {
                return 'verified';
            }
        }
    }

    /**
     * @return string
     */
    public function verifyWithLoop() : string
    {
        if (!isset(($this->data = $this->getData())->info->blockNumber)) {
            return $this->verifyWithLoop();
        } else {
            return $this->verify();
        }
    }

    /**
     * @param string $receiver
     * @param float $amount
     * @param string|null $tokenAddress
     * @return string
     */
    public function verifyData(string $receiver, float $amount, ?string $tokenAddress = null) : string
    {
        $receiver = strtolower($receiver);
        if (is_null($tokenAddress) || $tokenAddress == 'TRX') {
            $params = $this->data->raw_data->contract[0]->parameter->value;
            $data = (object) [
                "receiver" => strtolower($this->connection->tron->fromHex($params->to_address)),
                "amount" => floatval(Utils::toDec($params->amount, 6))
            ];

            if ($data->receiver == $receiver && strval($data->amount) == strval($amount)) {
                return 'verified';
            }
        } else {
            $decodedInput = $this->decodeInput();
            $token = $this->connection->tron->contract($tokenAddress);
            
            $data = (object) [
                'receiver' => strtolower($decodedInput->receiver),
                'amount' => Utils::toDec($decodedInput->amount, $token->decimals())
            ];

            if ($data->receiver == $receiver && strval($data->amount) == strval($amount)) {
                return 'verified';
            }
        }
        
        return 'failed';
    }

    /**
     * @param string $receiver
     * @param float $amount
     * @param string|null $tokenAddress
     * @return string
     */
    public function verifyWithData(string $receiver, float $amount, ?string $tokenAddress = null) : string
    {
        $result = $this->verify();
        if ($result == 'verified') {
            return $this->verifyData($receiver, $amount, $tokenAddress);
        } else {
            return $result;
        }
    }

    /**
     * @return string
     */
    public function getUrl() 
    {
        $explorerUrl = $this->connection->network->explorer;
        $explorerUrl .= '#/transaction/' . $this->id;
        return $explorerUrl;
    }
}