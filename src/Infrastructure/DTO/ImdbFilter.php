<?php


namespace App\Infrastructure\DTO;


use JsonSerializable;

class ImdbFilter
{
    /**
     * @var int|null
     */
    private $page;

    /**
     * @var string[]|null
     */
    private $type;

    /**
     * @var float|null
     */
    private $rate;

    /**
     * @var int|null
     */
    private $nbRate;

    /**
     * @var string|null
     */
    private $sort;

    public function __construct(?int $page, ?array $type, ?float $rate, ?int $nbRate, ?string $sort)
    {
        $this->page   = $page;
        $this->type   = $type;
        $this->rate   = $rate;
        $this->nbRate = $nbRate;
        $this->sort   = $sort;
    }

    public function getPage(): ?int
    {
        return $this->page;
    }

    public function getType(): ?array
    {
        return $this->type;
    }

    public function getRate(): ?float
    {
        return $this->rate;
    }

    public function getNbRate(): ?int
    {
        return $this->nbRate;
    }

    public function getSort(): ?string
    {
        return $this->sort;
    }

    public function __toString()
    {
        return http_build_query(
            [
                'page'        => $this->page,
                'title_type'  => implode(',', $this->type),
                'user_rating' => $this->rate . ',',
                'num_votes'   => $this->nbRate . ',',
                'sort'        => $this->sort,
            ]
        );
    }
}
