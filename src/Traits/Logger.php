<?php

namespace Atticlab\Libface\Traits;

Trait Logger
{
    /**
     * Psr3 Logger
     *
     * @uses Psr\Log\LoggerInterface
     */
    private $_logger = null;

    /**
     * @param \Psr\Log\LoggerInterface $logger
     */
    public function setLogger(\Psr\Log\LoggerInterface $logger)
    {
        $this->_logger = $logger;
    }

    /**
     * @param $message
     * @param array $context
     * @return null
     */
    public function ldebug($message, $context = [])
    {
        if (empty($this->_logger)) {
            return null;
        }

        return $this->_logger->debug($message, $context);
    }

    /**
     * @param $message
     * @param array $context
     * @return null
     */
    public function linfo($message, $context = [])
    {
        if (empty($this->_logger)) {
            return null;
        }
        return $this->_logger->info($message, $context);
    }

    /**
     * @param $message
     * @param array $context
     * @return null
     */
    public function lnotice($message, $context = [])
    {
        if (empty($this->_logger)) {
            return null;
        }
        return $this->_logger->notice($message, $context);
    }

    /**
     * @param $message
     * @param array $context
     * @return null
     */
    public function lwarning($message, $context = [])
    {
        if (empty($this->_logger)) {
            return null;
        }
        return $this->_logger->warning($message, $context);
    }

    /**
     * @param $message
     * @param array $context
     * @return null
     */
    public function lerror($message, $context = [])
    {
        if (empty($this->_logger)) {
            return null;
        }
        return $this->_logger->error($message, $context);
    }

    /**
     * @param $message
     * @param array $context
     * @return null
     */
    public function lalert($message, $context = [])
    {
        if (empty($this->_logger)) {
            return null;
        }
        return $this->_logger->alert($message, $context);
    }

    /**
     * @param $message
     * @param array $context
     * @return null
     */
    public function lemergency($message, $context = [])
    {
        if (empty($this->_logger)) {
            return null;
        }
        return $this->_logger->emergency($message, $context);
    }
}


