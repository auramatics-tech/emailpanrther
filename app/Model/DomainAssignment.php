<?php

namespace Acelle\Model;

use Illuminate\Database\Eloquent\Model;
use Acelle\Library\Tool;
use File;


use Monolog\Logger;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Formatter\LineFormatter;

class DomainAssignment extends Model
{
    protected $table = 'domain_assignment';

    
    protected $logger;

    /**
     * Filter items.
     *
     * @return collect
     */
    public static function scopeFilter($query, $request)
    {
        $query = $query->select('domain_assignment.*');

        // filters
        $filters = $request->all();
        if (!empty($filters)) {
            if (!empty($filters['domain_registrar'])) {
                $query = $query->where('domain_assignment.domain_registrar', '=', $filters['domain_registrar']);
            }
            if (!empty($filters['postal_server'])) {
                $query = $query->where('domain_assignment.postal_server', '=', $filters['postal_server']);
            }
        }

        // Other filter
        if (!empty($request->customer_id)) {
            $query = $query->where('domain_assignment.customer_id', '=', $request->customer_id);
        }

        if (!empty($request->admin_id)) {
            $query = $query->where('domain_assignment.admin_id', '=', $request->admin_id);
        }
    }


    /**
     * Search items.
     *
     * @return collect
     */
    public static function scopeSearch($query, $keyword)
    {
        // Keyword
        if (!empty(trim($keyword))) {
            foreach (explode(' ', trim($keyword)) as $keyword) {
                $query = $query->where(function ($q) use ($keyword) {
                    $q->orwhere('domain_assignment.domain', 'like', '%' . $keyword . '%')
                        ->orWhere('domain_assignment.domain_registrar', 'like', '%' . $keyword . '%');
                });
            }
        }
    }

    public static function createFromArray($params)
    {
        $assignment = new self();
        // validation
        $validator = $assignment->validConnection($params);

        if ($validator->fails()) {
            return [$validator, $assignment]; // IMPORTANT, $server instance (not saved) is required by parent controller
        }

        if ($params['domain']) {
            $domains = explode("\r\n", $params['domain']);
            foreach ($domains as $domain) {
                if ($domain) {
                    $assignment = new self();
                    $assignment->customer_id = $params['customer_id'];
                    $assignment->domain = $domain;
                    $assignment->domain_registrar = $params['domain_registrar'];
                    $assignment->postal_server = $params['PostalServer'];
                    $assignment->mx_route = isset($params['mx_route']) ? $params['mx_route'] : 0;
                    $assignment->status = 'queued';
                    $assignment->save();
                }
            }
        }

        return [$validator, $assignment];
    }

    /**
     * Test connection.
     *
     * @return object
     */
    public function validConnection($params)
    {
        $validator = \Validator::make($params, $this->getRules(), $this->getCustomValidationError());

        return $validator;
    }

    /**
     * Get rules.
     *
     * @return string
     */
    public function getRules()
    {
        $rules = [
            'domain' => 'required',
            'domain_registrar' => 'required',
            'PostalServer' => 'required'
        ];

        return $rules;
    }


    public function getCustomValidationError()
    {
        return [];
    }


    public function isExtended()
    {
        return false;
    }

    /**
     * Domain Registrar.
     *
     * @return collection
     */
    public function getDomainRegistrar()
    {
        return $this->hasOne('Acelle\Model\DomainRegistrar', 'id', 'domain_registrar');
    }


    /**
     * Postal Server.
     *
     * @return collection
     */
    public function getPostalServer()
    {
        return $this->hasOne('Acelle\Model\PostalServer', 'id', 'postal_server');
    }

    
    public function logger()
    {
        if (!is_null($this->logger)) {
            return $this->logger;
        }

        $formatter = new LineFormatter("[%datetime%] %channel%.%level_name%: %message%\n");

        $logfile = $this->getLogFile();
        $stream = new RotatingFileHandler($logfile, 0, Logger::DEBUG);
        $stream->setFormatter($formatter);

        $pid = getmypid();
        $logger = new Logger($pid);
        $logger->pushHandler($stream);
        $this->logger = $logger;
        return $this->logger;
    }

    public function getLogFile()
    {
        $path = storage_path(join_paths('logs', php_sapi_name(), '/domain_assignment-' . $this->id . '.log'));
        return $path;
    }
}
