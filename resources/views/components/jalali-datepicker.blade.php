@props([
    'name',
    'value'       => '',
    'placeholder' => '۱۴۰۳/۰۱/۰۱',
    'id'          => null,
    'required'    => false,
    'extraClass'  => '',
    'withTime'    => false,   // true → نمایش انتخاب ساعت و دقیقه
])

@php
$inputId = ($id ?? 'jdp_') . Str::random(6);
@endphp

{{-- ─── Styles (only once via @once) ─────────────────────────────────────── --}}
@once
@push('head')
<style>
.jdp-wrap { position: relative; display: inline-block; width: 100%; }
.jdp-trigger { position: absolute; left: 0.65rem; top: 50%; transform: translateY(-50%); color: #94a3b8; cursor: pointer; background: none; border: none; padding: 0; line-height: 1; }
.jdp-trigger:hover { color: #6366f1; }
.jdp-panel {
    position: absolute; z-index: 9999; top: calc(100% + 6px); right: 0;
    width: 300px;
    background: #fff; border: 1px solid #e2e8f0;
    border-radius: 14px; box-shadow: 0 16px 48px rgba(15,23,42,0.14);
    padding: 1rem; user-select: none; direction: rtl;
    animation: jdpFade .15s ease;
}
@keyframes jdpFade { from { opacity:0; transform: translateY(-6px); } to { opacity:1; transform: translateY(0); } }
.jdp-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: .75rem; gap: .25rem; }
.jdp-nav { background: none; border: none; cursor: pointer; padding: .3rem .5rem; border-radius: 8px; color: #6b7280; font-size: .9rem; line-height: 1; }
.jdp-nav:hover { background: #f0f0ff; color: #4f46e5; }
.jdp-month-label { font-weight: 700; font-size: .95rem; color: #1e293b; flex: 1; text-align: center; letter-spacing: 0; }
.jdp-year-label { font-size: .85rem; color: #6366f1; font-weight: 600; cursor: pointer; border-bottom: 1px dashed #c7d2fe; padding-bottom: 1px; }
.jdp-grid { display: grid; grid-template-columns: repeat(7, 1fr); gap: 2px; }
.jdp-dow { text-align: center; font-size: .7rem; font-weight: 700; color: #94a3b8; padding: .35rem 0 .5rem; }
.jdp-dow.friday { color: #e11d48; }
.jdp-day {
    text-align: center; font-size: .82rem; padding: .38rem .1rem; border-radius: 8px;
    cursor: pointer; color: #374151; transition: all .13s;
}
.jdp-day:hover { background: #eef2ff; color: #4f46e5; }
.jdp-day.today { font-weight: 700; color: #6366f1; }
.jdp-day.selected { background: #6366f1 !important; color: #fff !important; font-weight: 700; }
.jdp-day.blank, .jdp-day.disabled { cursor: default; color: #cbd5e1; }
.jdp-day.blank:hover, .jdp-day.disabled:hover { background: transparent; }
.jdp-day.friday-col { color: #e11d48; }
.jdp-day.selected.friday-col { color: #fff !important; }
.jdp-footer { display: flex; justify-content: space-between; align-items: center; margin-top: .75rem; padding-top: .75rem; border-top: 1px solid #f1f5f9; }
.jdp-today-btn { font-size: .75rem; color: #6366f1; background: none; border: none; cursor: pointer; font-weight: 600; padding: .25rem .5rem; border-radius: 6px; }
.jdp-today-btn:hover { background: #eef2ff; }
.jdp-clear-btn { font-size: .75rem; color: #94a3b8; background: none; border: none; cursor: pointer; padding: .25rem .5rem; border-radius: 6px; }
.jdp-clear-btn:hover { background: #f8fafc; color: #e11d48; }
.jdp-year-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 4px; margin-bottom: .5rem; }
.jdp-year-item { text-align:center; padding: .4rem .1rem; border-radius: 8px; font-size: .8rem; cursor:pointer; color:#374151; }
.jdp-year-item:hover { background:#eef2ff; color:#4f46e5; }
.jdp-year-item.selected { background:#6366f1; color:#fff; font-weight:700; }
.jdp-time-row { display: flex; align-items: center; gap: .5rem; margin-top: .6rem; padding-top: .6rem; border-top: 1px solid #f1f5f9; direction: ltr; }
.jdp-time-row input { width: 52px; text-align: center; padding: .3rem .4rem; border: 1px solid #e2e8f0; border-radius: 8px; font-size: .85rem; }
.jdp-time-sep { color: #94a3b8; font-weight: 700; }
.jdp-time-lbl { font-size: .75rem; color: #94a3b8; margin-right: auto; }
</style>
@endpush
@endonce

{{-- ─── Script (only once) ──────────────────────────────────────────────── --}}
@once
@push('head')
<script>
(function(){
// ── Jalali ↔ Gregorian conversion (jalaali-js algorithm) ────────────────
function j2g(jy,jm,jd){
    var j_y=jy-979, j_m=jm-1, j_d=jd-1, i;
    var j_day_no=365*j_y+Math.floor(j_y/33)*8+Math.floor((j_y%33+3)/4);
    var jml=[31,31,31,31,31,31,30,30,30,30,30,29];
    for(i=0;i<j_m;i++) j_day_no+=jml[i];
    j_day_no+=j_d;
    var g_day_no=j_day_no+79;
    var gy=1600+400*Math.floor(g_day_no/146097); g_day_no%=146097;
    var leap=true;
    if(g_day_no>=36525){ g_day_no--; gy+=100*Math.floor(g_day_no/36524); g_day_no%=36524; if(g_day_no>=365) g_day_no++; else leap=false; }
    gy+=4*Math.floor(g_day_no/1461); g_day_no%=1461;
    if(g_day_no>=366){ leap=false; gy++; g_day_no--; }
    var gml=[31,29,31,30,31,30,31,31,30,31,30,31], gm;
    for(gm=0;g_day_no>=gml[gm]+(gm===1&&leap?1:0);gm++) g_day_no-=gml[gm]+(gm===1&&leap?1:0);
    return[gy, gm+1, g_day_no+1];
}
function g2j(gy,gm,gd){
    var g_y=gy-1600, g_m=gm-1, g_d=gd-1, i;
    var g_d_no=365*g_y+Math.floor((g_y+3)/4)-Math.floor((g_y+99)/100)+Math.floor((g_y+399)/400);
    var gmo=[31,28,31,30,31,30,31,31,30,31,30,31];
    for(i=0;i<g_m;i++) g_d_no+=gmo[i];
    if(g_m>1&&((g_y%4===0&&g_y%100!==0)||(g_y%400===0))) g_d_no++;
    g_d_no+=g_d;
    var j_d_no=g_d_no-79, j_np=Math.floor(j_d_no/12053); j_d_no%=12053;
    var jy=979+33*j_np+4*Math.floor(j_d_no/1461); j_d_no%=1461;
    if(j_d_no>=366){jy+=Math.floor((j_d_no-1)/365);j_d_no=(j_d_no-1)%365;}
    var ml=[31,31,31,31,31,31,30,30,30,30,30], i;
    for(i=0;i<11&&j_d_no>=ml[i];i++)j_d_no-=ml[i];
    return[jy,i+1,j_d_no+1];
}
function jMonthDays(jy,jm){
    if(jm<=6)return 31;
    if(jm<=11)return 30;
    // اسفند: تشخیص کبیسه
    var leap=j2g(jy,12,29);
    var next=j2g(jy,12,30);
    if(next[0]===leap[0]&&next[1]===leap[1]&&next[2]===leap[2]+1)return 30;
    return 29;
}
function jDowOf(jy,jm,jd){ // 0=شنبه … 6=جمعه
    var g=j2g(jy,jm,jd);
    return (new Date(g[0],g[1]-1,g[2]).getDay()+1)%7;
}
function todayJalali(){
    var n=new Date(); return g2j(n.getFullYear(),n.getMonth()+1,n.getDate());
}
function toPersian(n){ return (''+n).replace(/[0-9]/g,d=>'۰۱۲۳۴۵۶۷۸۹'[d]); }
function toEnglish(s){ return (s+'').replace(/[۰-۹]/g,d=>d.charCodeAt(0)-1776); }

// ── Alpine component factory ─────────────────────────────────────────────
window.jalaliDatepicker = function(initialValue, withTime){
    var months=['فروردین','اردیبهشت','خرداد','تیر','مرداد','شهریور','مهر','آبان','آذر','دی','بهمن','اسفند'];
    var dows=['ش','ی','د','س','چ','پ','ج'];
    return {
        isOpen: false,
        showYearGrid: false,
        withTime: withTime||false,
        displayValue: initialValue||'',
        selY:null, selM:null, selD:null,
        viewY:null, viewM:null,
        timeH:'00', timeMin:'00',
        yearGridBase: null,

        init(){
            var t=todayJalali();
            this.viewY=t[0]; this.viewM=t[1];
            if(this.displayValue) this._parseValue(this.displayValue);
        },
        _parseValue(v){
            var s=toEnglish(v||'').trim();
            var m=s.match(/^(\d{4})[\/\-](\d{1,2})[\/\-](\d{1,2})(?:\s+(\d{1,2}):(\d{2}))?$/);
            if(m){
                this.selY=+m[1]; this.selM=+m[2]; this.selD=+m[3];
                this.viewY=this.selY; this.viewM=this.selM;
                if(m[4]){ this.timeH=m[4].padStart(2,'0'); this.timeMin=(m[5]||'00').padStart(2,'0'); }
            }
        },
        open(){ this.isOpen=true; this.showYearGrid=false; },
        close(){ this.isOpen=false; this.showYearGrid=false; },
        toggle(){ this.isOpen?this.close():this.open(); },
        onInput(v){
            this.displayValue=v;
            this._parseValue(v);
        },
        prevMonth(){
            if(this.viewM===1){
                if(this.viewY<=1405) return; // محدوده شروع
                this.viewM=12; this.viewY--;
            } else this.viewM--;
            this.showYearGrid=false;
        },
        nextMonth(){
            if(this.viewM===12){
                if(this.viewY>=1425) return; // محدوده پایان
                this.viewM=1; this.viewY++;
            } else this.viewM++;
            this.showYearGrid=false;
        },
        canPrevMonth(){ return !(this.viewY===1405 && this.viewM===1); },
        canNextMonth(){ return !(this.viewY===1425 && this.viewM===12); },
        monthLabel(){ return months[this.viewM-1]; },
        yearLabel(){ return toPersian(this.viewY); },
        dowHeaders(){ return dows; },
        calDays(){
            var total=jMonthDays(this.viewY,this.viewM);
            var offset=jDowOf(this.viewY,this.viewM,1); // 0=شنبه
            var cells=[];
            for(var i=0;i<offset;i++) cells.push({d:0,blank:true});
            for(var d=1;d<=total;d++) cells.push({d:d,blank:false});
            return cells;
        },
        isToday(d){
            var t=todayJalali();
            return d===t[2]&&this.viewM===t[1]&&this.viewY===t[0];
        },
        isSelected(d){
            return d===this.selD&&this.viewM===this.selM&&this.viewY===this.selY;
        },
        select(d){
            if(!d)return;
            this.selY=this.viewY; this.selM=this.viewM; this.selD=d;
            this._setDisplay();
            if(!this.withTime) this.close();
        },
        _setDisplay(){
            var v=toPersian(this.selY)+'/'+toPersian(String(this.selM).padStart(2,'0'))+'/'+toPersian(String(this.selD).padStart(2,'0'));
            if(this.withTime) v+=' '+this.timeH+':'+this.timeMin;
            this.displayValue=v;
        },
        selectToday(){
            var t=todayJalali();
            this.viewY=t[0]; this.viewM=t[1];
            this.selY=t[0]; this.selM=t[1]; this.selD=t[2];
            this._setDisplay();
            if(!this.withTime) this.close();
        },
        clear(){
            this.selY=null;this.selM=null;this.selD=null;
            this.displayValue='';
            this.close();
        },
        onTimeChange(){
            if(this.selY) this._setDisplay();
        },
        // Year grid — fixed range 1405..1425
        toggleYearGrid(){ this.showYearGrid=!this.showYearGrid; },
        yearGridItems(){
            var items=[];
            for(var y=1405;y<=1425;y++) items.push(y);
            return items;
        },
        selectYear(y){ this.viewY=y; this.viewM=this.viewM||1; this.showYearGrid=false; },
        toPersian(n){ return toPersian(n); },
    };
};
})();
</script>
@endpush
@endonce

{{-- ─── Component HTML ──────────────────────────────────────────────────── --}}
<div
    x-data="jalaliDatepicker('{{ $value }}', {{ $withTime ? 'true' : 'false' }})"
    x-init="init()"
    class="jdp-wrap">

    {{-- Input + calendar icon --}}
    <div class="relative">
        <input
            type="text"
            name="{{ $name }}"
            id="{{ $inputId }}"
            x-model="displayValue"
            @input="onInput($event.target.value)"
            @focus="open()"
            @keydown.escape="close()"
            @keydown.tab="close()"
            class="form-control pl-9 {{ $extraClass }}"
            dir="ltr"
            placeholder="{{ $placeholder }}"
            autocomplete="off"
            {{ $required ? 'required' : '' }}>
        <button type="button"
                @click.prevent="toggle()"
                class="jdp-trigger"
                tabindex="-1"
                aria-label="انتخاب تاریخ شمسی">
            <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24">
                <rect x="3" y="4" width="18" height="18" rx="3"/>
                <path d="M16 2v4M8 2v4M3 10h18"/>
            </svg>
        </button>
    </div>

    {{-- Calendar panel --}}
    <div x-show="isOpen"
         x-cloak
         @click.outside="close()"
         class="jdp-panel"
         style="display:none">

        {{-- ── Year grid (fixed 1405–1425, no pagination) ── --}}
        <template x-if="showYearGrid">
            <div>
                <div class="jdp-header" style="margin-bottom:.5rem">
                    <button type="button" class="jdp-nav" @click.prevent="showYearGrid=false">&#x2715;</button>
                    <span class="jdp-month-label text-sm">انتخاب سال (۱۴۰۵ – ۱۴۲۵)</span>
                </div>
                <div class="jdp-year-grid">
                    <template x-for="y in yearGridItems()" :key="y">
                        <div class="jdp-year-item"
                             :class="{'selected': y===viewY}"
                             @click="selectYear(y)"
                             x-text="toPersian(y)">
                        </div>
                    </template>
                </div>
            </div>
        </template>

        {{-- ── Month calendar ──────────────────────────── --}}
        <template x-if="!showYearGrid">
            <div>
                {{-- Header: month nav --}}
                <div class="jdp-header">
                    <button type="button" class="jdp-nav"
                            @click.prevent="prevMonth()"
                            :class="{'opacity-30 cursor-not-allowed': !canPrevMonth()}"
                            :disabled="!canPrevMonth()">&#8249;</button>
                    <div class="jdp-month-label flex items-center justify-center gap-2">
                        <span x-text="monthLabel()"></span>
                        <span class="jdp-year-label" @click="toggleYearGrid()" x-text="yearLabel()"></span>
                    </div>
                    <button type="button" class="jdp-nav"
                            @click.prevent="nextMonth()"
                            :class="{'opacity-30 cursor-not-allowed': !canNextMonth()}"
                            :disabled="!canNextMonth()">&#8250;</button>
                </div>

                {{-- Day-of-week headers --}}
                <div class="jdp-grid">
                    <template x-for="(dw, idx) in dowHeaders()" :key="idx">
                        <div class="jdp-dow" :class="{'friday': idx===6}" x-text="dw"></div>
                    </template>
                </div>

                {{-- Day cells --}}
                <div class="jdp-grid">
                    <template x-for="(cell, idx) in calDays()" :key="idx">
                        <div
                            class="jdp-day"
                            :class="{
                                'blank':    cell.blank,
                                'today':    !cell.blank && isToday(cell.d),
                                'selected': !cell.blank && isSelected(cell.d),
                                'friday-col': idx % 7 === 6
                            }"
                            @click="select(cell.d)"
                            x-text="cell.blank ? '' : toPersian(cell.d)">
                        </div>
                    </template>
                </div>

                {{-- Time picker (optional) --}}
                @if($withTime)
                <div class="jdp-time-row">
                    <span class="jdp-time-lbl">ساعت:</span>
                    <input type="number" x-model="timeMin" @input="onTimeChange()" min="0" max="59" placeholder="۰۰">
                    <span class="jdp-time-sep">:</span>
                    <input type="number" x-model="timeH"   @input="onTimeChange()" min="0" max="23" placeholder="۰۰">
                </div>
                @endif

                {{-- Footer: امروز / پاک کردن --}}
                <div class="jdp-footer">
                    <button type="button" class="jdp-today-btn" @click.prevent="selectToday()">امروز</button>
                    <button type="button" class="jdp-clear-btn" @click.prevent="clear()">پاک کردن</button>
                </div>
            </div>
        </template>

    </div>
</div>
