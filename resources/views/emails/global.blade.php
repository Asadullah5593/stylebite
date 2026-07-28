@extends('emails.layout')

@section('content')
    <h2 style="margin:0 0 16px; font-size:24px; line-height:1.3; color:#111827;">{{ $heading }}</h2>

    <div style="font-size:15px; line-height:1.8; color:#374151;">
        {!! nl2br(e($contentText)) !!}
    </div>

    @if (! empty($highlightCode))
        <div style="margin:24px 0; text-align:center;">
            <span style="display:inline-block; background-color:#fff4f0; border:1px solid #ffd9cc; border-radius:12px; padding:18px 32px; font-size:36px; font-weight:800; letter-spacing:10px; color:#111827; font-family:'Courier New', Courier, monospace;">{{ $highlightCode }}</span>
        </div>
    @endif

    @if ($actionText && $actionUrl)
        <div style="margin-top:28px;">
            <a href="{{ $actionUrl }}" style="display:inline-block; background-color:#ff7a59; color:#ffffff; text-decoration:none; padding:14px 22px; border-radius:10px; font-weight:700;">
                {{ $actionText }}
            </a>
        </div>
        <p style="margin:20px 0 0; font-size:13px; line-height:1.6; color:#6b7280; word-break:break-all;">
            If the button does not work, use this link:<br>
            <a href="{{ $actionUrl }}" style="color:#ff7a59;">{{ $actionUrl }}</a>
        </p>
    @endif

    <p style="margin:32px 0 0; font-size:15px; line-height:1.8; color:#374151;">
        Best Regards,<br>
        Stylebite Team
    </p>
@endsection
