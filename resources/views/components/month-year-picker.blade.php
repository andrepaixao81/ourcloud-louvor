@props([
    'month',
    'routeName',
    'query' => [],
    'id' => 'sgml-month-picker',
    'parameter' => 'month',
])
@php
    $monthNames = [1=>'Janeiro',2=>'Fevereiro',3=>'Março',4=>'Abril',5=>'Maio',6=>'Junho',7=>'Julho',8=>'Agosto',9=>'Setembro',10=>'Outubro',11=>'Novembro',12=>'Dezembro'];
    $initialYear = (int) $month->year;
    $initialMonth = (int) $month->month;
@endphp

<button type="button" class="sgml-btn-secondary min-w-52 justify-center" data-month-picker-open="{{ $id }}" aria-haspopup="dialog">
    <span>{{ $monthNames[$initialMonth] }} {{ $initialYear }}</span>
    <span class="ml-2 text-slate-400">▾</span>
</button>

<dialog id="{{ $id }}" class="m-auto w-[min(94vw,520px)] rounded-2xl p-0 shadow-2xl backdrop:bg-slate-950/40">
    <div class="bg-white p-6">
        <div class="flex items-start justify-between gap-3">
            <div>
                <h2 class="text-xl font-black">Escolher mês e ano</h2>
                <p class="mt-1 text-sm text-slate-500">Primeiro escolha o ano e depois clique no mês. Não é necessário digitar a data.</p>
            </div>
            <button type="button" class="rounded-lg px-3 py-1 text-xl text-slate-400 hover:bg-slate-100" data-month-picker-close="{{ $id }}">×</button>
        </div>

        <div class="mt-5">
            <div class="mb-2 text-xs font-bold uppercase tracking-wide text-slate-500">Ano</div>
            <div class="flex flex-wrap gap-2" data-year-options>
                @for($year = $initialYear - 2; $year <= $initialYear + 2; $year++)
                    <button type="button" data-year="{{ $year }}" class="rounded-xl border px-4 py-2 text-sm font-bold {{ $year === $initialYear ? 'border-indigo-600 bg-indigo-50 text-indigo-700' : 'border-slate-200 bg-white text-slate-700 hover:border-indigo-300' }}">{{ $year }}</button>
                @endfor
            </div>
            <div class="mt-3 flex items-center gap-2 text-sm text-slate-500">
                <button type="button" class="sgml-btn-secondary !px-3 !py-1.5" data-year-shift="-5">← 5 anos</button>
                <button type="button" class="sgml-btn-secondary !px-3 !py-1.5" data-year-shift="5">5 anos →</button>
            </div>
        </div>

        <div class="mt-6">
            <div class="mb-2 text-xs font-bold uppercase tracking-wide text-slate-500">Mês</div>
            <div class="grid grid-cols-2 gap-2 sm:grid-cols-3">
                @foreach($monthNames as $number => $label)
                    <button type="button" data-month="{{ $number }}" class="rounded-xl border px-3 py-3 text-left text-sm font-semibold {{ $number === $initialMonth ? 'border-indigo-200 bg-indigo-50 text-indigo-800' : 'border-slate-200 hover:border-indigo-300 hover:bg-indigo-50' }}">{{ $label }}</button>
                @endforeach
            </div>
        </div>
    </div>
</dialog>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const dialog = document.getElementById(@json($id));
    if (!dialog || dialog.dataset.ready === '1') return;
    dialog.dataset.ready = '1';

    const baseUrl = @json(route($routeName));
    const fixedQuery = @json($query);
    let selectedYear = {{ $initialYear }};
    let yearWindowStart = {{ $initialYear - 2 }};

    const renderYears = () => {
        const holder = dialog.querySelector('[data-year-options]');
        if (!holder) return;
        holder.innerHTML = '';
        for (let year = yearWindowStart; year < yearWindowStart + 5; year++) {
            const button = document.createElement('button');
            button.type = 'button';
            button.dataset.year = String(year);
            button.textContent = String(year);
            button.className = 'rounded-xl border px-4 py-2 text-sm font-bold ' + (year === selectedYear
                ? 'border-indigo-600 bg-indigo-50 text-indigo-700'
                : 'border-slate-200 bg-white text-slate-700 hover:border-indigo-300');
            button.addEventListener('click', () => {
                selectedYear = year;
                renderYears();
            });
            holder.appendChild(button);
        }
    };

    document.querySelectorAll('[data-month-picker-open="' + @json($id) + '"]').forEach((button) => {
        button.addEventListener('click', () => dialog.showModal());
    });
    dialog.querySelectorAll('[data-month-picker-close="' + @json($id) + '"]').forEach((button) => {
        button.addEventListener('click', () => dialog.close());
    });
    dialog.querySelectorAll('[data-year]').forEach((button) => {
        button.addEventListener('click', () => {
            selectedYear = Number(button.dataset.year);
            renderYears();
        });
    });
    dialog.querySelectorAll('[data-year-shift]').forEach((button) => {
        button.addEventListener('click', () => {
            yearWindowStart += Number(button.dataset.yearShift);
            selectedYear = yearWindowStart + 2;
            renderYears();
        });
    });
    dialog.querySelectorAll('[data-month]').forEach((button) => {
        button.addEventListener('click', () => {
            const monthNumber = String(button.dataset.month).padStart(2, '0');
            const params = new URLSearchParams();
            Object.entries(fixedQuery || {}).forEach(([key, value]) => {
                if (value !== null && value !== undefined && value !== '') params.set(key, String(value));
            });
            const value = @json($parameter) === 'date' ? (selectedYear + '-' + monthNumber + '-01') : (selectedYear + '-' + monthNumber);
            params.set(@json($parameter), value);
            window.location.href = baseUrl + '?' + params.toString();
        });
    });
});
</script>
