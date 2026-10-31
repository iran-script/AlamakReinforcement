@extends('layouts.app')
@section('title','طراح گردش‌کار · نسخه '.$workflowVersion->version)
@include('platform.workflows._assets')
@section('content')
<main class="workflow-studio ws-designer" data-workflow-studio data-designer data-version-id="{{ $workflowVersion->id }}" data-editable="{{ $editable?'1':'0' }}"><header class="ws-designer-head"><div><a href="{{ route('platform.workflows.show',$workflowVersion->definition) }}">{{ $workflowVersion->definition->name }}</a><h1>طراح گردش‌کار · نسخه {{ $workflowVersion->version }}</h1></div><div class="ws-actions"><span class="ws-unsaved" data-unsaved hidden>ذخیره‌نشده</span><a class="ws-btn" href="{{ route('platform.workflows.preview',$workflowVersion) }}">پیش‌نمایش</a>@if($editable)<button class="ws-btn" data-auto-layout>چیدمان خودکار</button><button class="ws-btn" data-validate>اعتبارسنجی</button><button class="ws-btn ws-primary" data-save>ذخیره پیش‌نویس</button><button class="ws-btn ws-success" data-publish>انتشار</button>@elseif($workflowVersion->status===App\Enums\WorkflowVersionStatus::Published)@can('publish',$workflowVersion)<button class="ws-btn ws-primary" data-clone="{{ $workflowVersion->id }}">تکثیر به پیش‌نویس</button>@endcan @endif</div></header>
@unless($editable)<div class="ws-alert ws-warning"><strong>حالت فقط‌خواندنی:</strong> نسخه {{ $workflowVersion->status===App\Enums\WorkflowVersionStatus::Published?'منتشرشده و تغییرناپذیر':'بایگانی‌شده' }} است.</div>@endunless<div class="ws-alert ws-danger" data-errors hidden tabindex="-1"></div><div class="ws-alert ws-success-alert" data-success hidden></div><nav class="ws-tabs"><button data-tab="palette">اجزا</button><button class="active" data-tab="canvas">نمودار</button><button data-tab="inspector">ویژگی‌ها</button></nav>
<div class="ws-workspace"><aside class="ws-palette" data-panel="palette"><div class="ws-panel-head"><h2>اجزای گردش‌کار</h2></div><button data-add-state="initial" @disabled(!$editable)><i class="bi bi-play-circle"></i><span><strong>شروع</strong><small>تنها نقطه آغاز گردش‌کار</small></span></button><button data-add-state="intermediate" @disabled(!$editable)><i class="bi bi-gear"></i><span><strong>مرحله عملیاتی</strong><small>پردازش یا تصمیم میانی</small></span></button><button data-add-state="terminal" data-outcome="completed" @disabled(!$editable)><i class="bi bi-check-circle"></i><span><strong>پایان موفق / بسته‌شده</strong><small>اتمام موفق فرایند</small></span></button><button data-add-state="terminal" data-outcome="cancelled" @disabled(!$editable)><i class="bi bi-x-circle"></i><span><strong>پایان لغوشده</strong><small>پایان بدون تکمیل</small></span></button><hr><button data-add-transition @disabled(!$editable)><i class="bi bi-arrow-left-right"></i><span><strong>انتقال</strong><small>ابتدا مبدأ و مقصد را انتخاب کنید</small></span></button><div class="ws-graph-hints" data-hints></div></aside>
<section class="ws-canvas-panel active" data-panel="canvas"><div class="ws-canvas-tools"><span data-counts></span><span>نمودار مفهومی؛ منطق از وضعیت‌ها و انتقال‌ها ذخیره می‌شود.</span></div><div class="ws-canvas" data-canvas><svg data-connectors aria-hidden="true"><defs><marker id="ws-arrow" markerWidth="8" markerHeight="8" refX="7" refY="4" orient="auto"><path d="M0,0 L8,4 L0,8 z"/></marker></defs></svg><div data-nodes></div></div></section><aside class="ws-inspector" data-panel="inspector"><div class="ws-panel-head"><h2>ویژگی‌ها</h2><span data-selection>انتخاب نشده</span></div><div data-inspector></div></aside></div>
@php
    $workflowPayload = [
        'status' => $workflowVersion->status->value,
        'states' => $workflowVersion->states,
        'transitions' => $workflowVersion->transitions,
        'forms' => $formKeys,
        'assignees' => [
            'users' => $assignableUsers,
            'roles' => $assignableRoles,
            'user_assignment_available' => $organizationId !== null,
        ],
    ];
@endphp
<script type="application/json" data-workflow-payload>{!! json_encode($workflowPayload, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) !!}</script></main>
@endsection
