{{--
    Shared logo + school header for reports that don't already own one.
    Renders three columns: left logo, centered school name & address, right
    optional title slot. Falls back gracefully to a blank left cell when the
    school has no logo uploaded so the layout stays symmetrical.

    Params:
      - $school       (App\Models\School|null)  the school to brand with
      - $title        (string|null)             optional big heading line
      - $subtitle     (string|null)             optional smaller line under
      - $logoSize     (int)                     px, defaults to 55
--}}
@php
    // Delegate to School::getLogoAbsolutePath — checks storage:link, direct
    // storage_path, /uploads legacy, and a couple of cPanel-flavoured
    // fallbacks so PDF renders find the file whichever way the deploy
    // ended up laying out /public.
    $logoPath = method_exists($school, 'getLogoAbsolutePath')
        ? $school->getLogoAbsolutePath()
        : null;
    $logoSize = $logoSize ?? 55;
@endphp

<table class="report-logo-header" style="width:100%; border-collapse:collapse; margin-bottom:8px;">
    <tr>
        <td style="width:{{ $logoSize + 10 }}px; vertical-align:middle; text-align:center;">
            @if($logoPath)
                {{-- file:// prefix guarantees DomPDF treats it as an
                     on-disk path, not a URL to fetch (fetching would
                     fail behind auth on the same host). --}}
                <img src="file://{{ $logoPath }}" alt=""
                     style="width:{{ $logoSize }}px; height:{{ $logoSize }}px; object-fit:contain;">
            @else
                <div style="width:{{ $logoSize }}px; height:{{ $logoSize }}px; background:#1e3a8a;
                            border-radius:50%; color:#fff; font-size:{{ round($logoSize * 0.35) }}px;
                            font-weight:bold; line-height:{{ $logoSize }}px; text-align:center;
                            margin:0 auto;">
                    {{ strtoupper(mb_substr($school?->code ?? $school?->name ?? 'S', 0, 2)) }}
                </div>
            @endif
        </td>
        <td style="text-align:center; vertical-align:middle;">
            @if(!empty($school?->name))
                <div style="font-size:15pt; font-weight:bold; text-transform:uppercase; letter-spacing:0.5px;">
                    {{ $school->name }}
                </div>
            @endif
            @if(!empty($school?->address))
                <div style="font-size:8.5pt; color:#334155; margin-top:1px;">{{ $school->address }}</div>
            @endif
            @if(!empty($title))
                <div style="font-size:11.5pt; font-weight:bold; color:#1e3a8a; margin-top:4px;">
                    {{ $title }}
                </div>
            @endif
            @if(!empty($subtitle))
                <div style="font-size:8.5pt; color:#475569; margin-top:1px;">{{ $subtitle }}</div>
            @endif
        </td>
        <td style="width:{{ $logoSize + 10 }}px;"></td>
    </tr>
</table>
<div style="border-bottom:2px solid #1e3a8a; margin-bottom:10px;"></div>
