<?php

declare(strict_types=1);

namespace Leevel\Page;

use Leevel\Support\Arr\ConvertJson;
use Leevel\Support\IArray;
use Leevel\Support\IHtml;
use Leevel\Support\IJson;

/**
 * 分页处理.
 */
class Page implements IJson, IArray, IHtml, \JsonSerializable
{
    /**
     * 默认每页分页数量.
     */
    public const PER_PAGE = 15;

    /**
     * 无穷大记录数.
     */
    public const MACRO = 999999999;

    /**
     * 默认分页渲染.
     */
    public const RENDER = 'render';

    /**
     * 默认范围.
     */
    public const RANGE = 2;

    /**
     * 总记录数量.
     */
    protected ?int $totalRecord = null;

    /**
     * 每页分页数量.
     */
    protected ?int $perPage = null;

    /**
     * 当前分页页码.
     */
    protected int $currentPage;

    /**
     * 总页数.
     */
    protected ?int $totalPage = null;

    /**
     * 分页开始位置.
     */
    protected ?int $pageStart = null;

    /**
     * 分页结束位置.
     */
    protected ?int $pageEnd = null;

    /**
     * 缓存 URL 地址.
     */
    protected ?string $cachedUrl = null;

    /**
     * 配置.
     */
    protected array $config = [
        'page' => 'page',
        'range' => 2,
        'render' => 'render',
        'render_config' => [],
        'url' => null,
        'param' => [],
        'fragment' => null,
    ];

    /**
     * 构造函数.
     *
     * @throws \InvalidArgumentException
     */
    public function __construct(int $currentPage, ?int $perPage = null, ?int $totalRecord = null, array $config = [])
    {
        if ($currentPage < 1) {
            throw new \InvalidArgumentException('Current page must great than 0.');
        }

        $this->currentPage = $currentPage;
        $this->perPage = $perPage;
        $this->totalRecord = $totalRecord;
        $this->config = array_merge($this->config, $config);
    }

    /**
     * 实现魔术方法 __toString.
     */
    public function __toString(): string
    {
        return (string) $this->render();
    }

    /**
     * {@inheritDoc}
     */
    public function toHtml(): string
    {
        return (string) $this->render();
    }

    /**
     * 追加分页条件.
     */
    public function append(string $key, string $value): self
    {
        return $this->addParam($key, $value);
    }

    /**
     * 批量追加分页条件.
     */
    public function appends(array $values): self
    {
        foreach ($values as $key => $value) {
            $this->addParam($key, $value);
        }

        return $this;
    }

    /**
     * 设置分页条件.
     */
    public function param(array $param): self
    {
        $this->config['param'] = $param;

        return $this;
    }

    /**
     * 添加分页条件.
     */
    public function addParam(string $key, mixed $value): self
    {
        $tmp = $this->config['param'];
        $tmp[$key] = $value;
        $this->config['param'] = $tmp;

        return $this;
    }

    /**
     * 设置渲染参数.
     */
    public function renderConfig(string $key, mixed $value): self
    {
        $tmp = $this->config['render_config'];
        $tmp[$key] = $value;
        $this->config['render_config'] = $tmp;

        return $this;
    }

    /**
     * 批量设置渲染参数.
     */
    public function renderConfigs(array $config): self
    {
        foreach ($config as $key => $value) {
            $this->renderConfig($key, $value);
        }

        return $this;
    }

    /**
     * 设置 URL.
     */
    public function url(?string $url = null): self
    {
        $this->config['url'] = $url;

        return $this;
    }

    /**
     * 设置渲染组件.
     */
    public function setRender(?string $render = null): self
    {
        $this->config['render'] = $render;

        return $this;
    }

    /**
     * 获取渲染组件.
     */
    public function getRender(): string
    {
        return $this->config['render'] ?: static::RENDER;
    }

    /**
     * 设置分页范围.
     */
    public function range(?int $range = null): self
    {
        $this->config['range'] = $range;

        return $this;
    }

    /**
     * 获取分页范围.
     */
    public function getRange(): int
    {
        return $this->config['range'] ?
            (int) $this->config['range'] :
            static::RANGE;
    }

    /**
     * 设置 URL 描点.
     */
    public function fragment(?string $fragment = null): self
    {
        $this->config['fragment'] = $fragment;

        return $this;
    }

    /**
     * 获取 URL 描点.
     */
    public function getFragment(): ?string
    {
        return $this->config['fragment'];
    }

    /**
     * 设置每页分页数量.
     */
    public function perPage(int $perPage): self
    {
        $this->perPage = $perPage;

        return $this;
    }

    /**
     * 返回每页数量.
     */
    public function getPerPage(): int
    {
        if (null === $this->perPage) {
            $this->perPage = static::PER_PAGE;
        }

        return $this->perPage;
    }

    /**
     * 设置分页名字.
     */
    public function pageName(string $pageName): self
    {
        $this->config['page'] = $pageName;

        return $this;
    }

    /**
     * 获取分页名字.
     */
    public function getPageName(): string
    {
        return $this->config['page'];
    }

    /**
     * 返回总记录数量.
     */
    public function getTotalRecord(): ?int
    {
        return $this->totalRecord;
    }

    /**
     * 是否为无限分页.
     */
    public function isTotalMacro(): bool
    {
        return $this->getTotalRecord() === static::MACRO;
    }

    /**
     * 取得第一个记录的编号.
     */
    public function getFromRecord(): int
    {
        return ($this->getCurrentPage() - 1) * $this->getPerPage();
    }

    /**
     * 取得最后一个记录的编号.
     */
    public function getToRecord(): ?int
    {
        if (!$this->canTotalRender()) {
            return null;
        }

        $to = $this->getFromRecord() + $this->getPerPage();

        return $to <= $this->getTotalRecord() ? $to : $this->getTotalRecord();
    }

    /**
     * 设置当前分页.
     */
    public function currentPage(int $page): void
    {
        $this->currentPage = $page;
    }

    /**
     * 返回当前分页.
     */
    public function getCurrentPage(): int
    {
        return $this->currentPage;
    }

    /**
     * 返回分页视图开始页码.
     */
    public function getPageStart(): int
    {
        if (null !== $this->pageStart) {
            return $this->pageStart;
        }

        $this->pageStart = $this->getCurrentPage() - $this->getRange();
        if ($this->pageStart < $this->getRange() * 2) {
            $this->pageStart = 1;
        }

        return $this->pageStart;
    }

    /**
     * 返回分页视图结束页码.
     */
    public function getPageEnd(): int
    {
        if (null !== $this->pageEnd) {
            return $this->pageEnd;
        }

        $this->pageEnd = $this->getCurrentPage() + $this->getRange();
        if (1 === $this->getPageStart()) {
            $this->pageEnd = $this->getRange() * 2 + 2;
        }

        if ($this->getTotalPage()
            && $this->pageEnd > $this->getTotalPage()) {
            $this->pageEnd = (int) $this->getTotalPage();
        }

        return $this->pageEnd;
    }

    /**
     * 返回总分页数量.
     */
    public function getTotalPage(): ?int
    {
        if (null !== $this->totalPage) {
            return $this->totalPage;
        }

        if (null === $this->getTotalRecord()) {
            return null;
        }

        return $this->totalPage = (int) ceil($this->getTotalRecord() / $this->getPerPage());
    }

    /**
     * 是否渲染 total.
     */
    public function canTotalRender(): bool
    {
        return null !== $this->getTotalRecord()
            && !$this->isTotalMacro();
    }

    /**
     * 是否渲染 first.
     */
    public function canFirstRender(): bool
    {
        return $this->getTotalPage() > 1
            && $this->getCurrentPage() >= ($this->getRange() * 2 + 2);
    }

    /**
     * 返回渲染 first.prev.
     */
    public function parseFirstRenderPrev(): int
    {
        return $this->getCurrentPage() - ($this->getRange() * 2 + 1);
    }

    /**
     * 是否渲染 prev.
     */
    public function canPrevRender(): bool
    {
        return (null === $this->getTotalPage() || $this->getTotalPage() > 1)
            && 1 !== $this->getCurrentPage();
    }

    /**
     * 返回渲染 prev.prev.
     */
    public function parsePrevRenderPrev(): int
    {
        return $this->getCurrentPage() - 1;
    }

    /**
     * 是否渲染 main.
     */
    public function canMainRender(): bool
    {
        return $this->getTotalPage() > 1;
    }

    /**
     * 是否渲染 next.
     */
    public function canNextRender(): bool
    {
        return null === $this->getTotalPage()
            || ($this->getTotalPage() > 1
                && $this->getCurrentPage() !== $this->getTotalPage());
    }

    /**
     * 是否渲染 last.
     */
    public function canLastRender(): bool
    {
        return $this->getTotalPage() > 1
            && $this->getCurrentPage() !== $this->getTotalPage()
            && $this->getTotalPage() > $this->getPageEnd();
    }

    /**
     * 是否渲染 last.
     */
    public function canLastRenderNext(): bool
    {
        return $this->getTotalPage() > ($this->getPageEnd() + 1);
    }

    /**
     * 返回渲染 last.next.
     */
    public function parseLastRenderNext(): int
    {
        $next = $this->getCurrentPage() + $this->getRange() * 2 + 1;

        if (!$this->isTotalMacro()
            && $next > $this->getTotalPage()) {
            $next = (int) $this->getTotalPage();
        }

        return $next;
    }

    /**
     * 替换分页变量.
     */
    public function pageReplace(int|string $page): string
    {
        return str_replace([urlencode('{page}'), '{page}'], (string) $page, $this->getUrl());
    }

    /**
     * 渲染分页.
     */
    public function render(null|IRender|string $render = null, array $config = []): string
    {
        $config = array_merge($this->config['render_config'], $config);

        if (null === $render || \is_string($render)) {
            $render = $render ?: $this->getRender();
            $render = __NAMESPACE__.'\\'.ucfirst($render);

            /** @var IRender $render */
            $render = new $render($this);
        }

        $result = $render->render($config);
        $this->cachedUrl = null;

        return $result;
    }

    /**
     * {@inheritDoc}
     */
    public function toArray(): array
    {
        return [
            'per_page' => $this->getPerPage(),
            'current_page' => $this->getCurrentPage(),
            'total_page' => $this->getTotalPage(),
            'total_record' => $this->getTotalRecord(),
            'total_macro' => $this->isTotalMacro(),
            'from' => $this->getFromRecord(),
            'to' => $this->getToRecord(),
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * {@inheritDoc}
     */
    public function toJson(?int $config = null): string
    {
        return ConvertJson::handle($this->jsonSerialize(), $config);
    }

    /**
     * 分析分页 URL 地址.
     *
     * - {page} 表示自定义分页变量替换.
     */
    protected function getUrl(): string
    {
        if (null !== $this->cachedUrl) {
            return $this->cachedUrl;
        }

        $url = (string) $this->config['url'];
        $param = $this->config['param'];
        if (isset($param[$this->config['page']])) {
            unset($param[$this->config['page']]);
        }
        if (!str_contains($url, '{page}')) {
            $param[$this->config['page']] = '{page}';
        }

        $this->cachedUrl = $url.
            (!str_contains($url, '?') ? '?' : '&').
            http_build_query($param, '', '&');

        return $this->cachedUrl .= $this->buildFragment();
    }

    /**
     * 创建描点.
     */
    protected function buildFragment(): string
    {
        return $this->getFragment() ? '#'.$this->getFragment() : '';
    }
}
