<?php
/**
 * 分页类
 */

class Pager
{
    private $total;
    private $size;
    private $page;
    private $pages;
    private $url;

    public function __construct($total, $page, $size = 20, $url = '')
    {
        $this->total = max(0, (int)$total);
        $this->size = max(1, (int)$size);
        $this->pages = max(1, (int)ceil($this->total / $this->size));
        $this->page = min(max(1, (int)$page), $this->pages);
        $this->url = $url !== '' ? $url : (isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '');
        // 去掉已有 page 参数
        $this->url = preg_replace('/([?&])page=\d+/', '$1', $this->url);
        $this->url = rtrim($this->url, '?&');
    }

    public function page()
    {
        return $this->page;
    }

    public function pages()
    {
        return $this->pages;
    }

    public function offset()
    {
        return ($this->page - 1) * $this->size;
    }

    public function limit()
    {
        return $this->size;
    }

    private function build($page)
    {
        $sep = strpos($this->url, '?') === false ? '?' : '&';
        return $this->url . $sep . 'page=' . $page;
    }

    public function html($max = 7)
    {
        if ($this->pages <= 1) {
            return '';
        }
        $html = '<nav class="pager" data-pager>';
        $html .= '<a class="pager-btn ' . ($this->page <= 1 ? 'disabled' : '') . '" href="' . e($this->build(max(1, $this->page - 1))) . '" data-page="' . max(1, $this->page - 1) . '">上一页</a>';
        $start = max(1, $this->page - (int)floor($max / 2));
        $end = min($this->pages, $start + $max - 1);
        $start = max(1, $end - $max + 1);
        for ($i = $start; $i <= $end; $i++) {
            $active = $i === $this->page ? 'active' : '';
            $html .= '<a class="pager-btn ' . $active . '" href="' . e($this->build($i)) . '" data-page="' . $i . '">' . $i . '</a>';
        }
        $html .= '<a class="pager-btn ' . ($this->page >= $this->pages ? 'disabled' : '') . '" href="' . e($this->build(min($this->pages, $this->page + 1))) . '" data-page="' . min($this->pages, $this->page + 1) . '">下一页</a>';
        $html .= '</nav>';
        return $html;
    }
}
