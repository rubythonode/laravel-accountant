<?php

namespace TomIrons\Accountant;

use Stripe\Stripe;
use LogicException;
use Illuminate\Support\Collection;

abstract class Client
{
    /**
     * Current data object.
     *
     * @var object
     */
    protected $class;

    /**
     * The number of items to be shown per page.
     *
     * @var int
     */
    protected $limit;

    /**
     * Current page/
     *
     * @var int
     */
    protected $currentPage;

    protected $name;

    /**
     * Create a new Client instance.
     *
     * @return void
     */
    public function __construct()
    {
        Stripe::setApiKey(config('services.stripe.key'));
        $this->limit = config('accountant.limit', 10);
    }

    public function getStripeClass()
    {
        return app('Stripe\\' . ucfirst($this->name));
    }

    /**
     * Paginate the results.
     *
     * @param string $path
     * @param string $query
     * @return Paginator
     */
    public function paginate($path, $query)
    {
        if ($this->class->object !== 'list' || ! is_array($this->class->data)) {
            throw new LogicException("Object must be a 'list' in order to paginate.");
        }

        $collection = new Collection($this->class->data);

        $this->points($collection->first()->id, $collection->last()->id);

        return new Paginator(
            $collection,
            $this->limit(),
            $this->currentPage(),
            compact('path', 'query')
        );
    }

    /**
     * Set the class for the client.
     *
     * @param string $name
     * @param $params
     * @return $this
     */
    protected function class()
    {
        $this->class = $this->getStripeClass()::all([
            'limit' => $this->limit(),
            'ending_before' => $this->end(),
            'starting_after' => $this->start(),
        ]);

        return $this;
    }

    /**
     * Get the 'starting_after' value for the API call.
     *
     * @return string
     */
    protected function start()
    {
        return request('start', null);
    }

    /**
     * Get the 'ending_before' value for the API call.
     *
     * @return string
     */
    protected function end()
    {
        return request('end', null);
    }

    /**
     * Get the number of items shown per page.
     *
     * @return int
     */
    protected function limit()
    {
        return $this->limit;
    }

    /**
     * Get / set the start and end points.
     *
     * @param string $start
     * @param string|null $end
     */
    protected function points($start, $end = null)
    {
        if (str_contains($start, ['start', 'end'])) {
            return session()->get('accountant.api.' . $start);
        }

        session()->put('accountant.api.start', $end);
        session()->put('accountant.api.end', $start);
    }

    /**
     * Get / set the current page.
     *
     * @param string|int $page
     * @return $this
     */
    public function currentPage($page = null)
    {
        if (is_null($page)) {
            return $this->currentPage;
        }

        $this->currentPage = $page;

        return $this;
    }

    public function all()
    {
        return $this->class();
    }

    public function __call(string $method, $args = null)
    {
        $this->getStripeClass()::$method($args);
    }



}