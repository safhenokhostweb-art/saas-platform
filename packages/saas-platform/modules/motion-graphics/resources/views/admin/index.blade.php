<div class='motion-graphics-admin' dir='rtl'>
    <h1>کتابخانه مستقل موشن گرافیک</h1>
    <p>این رابط فقط پس از اتصال رسمی Platform Admin و فعال‌سازی صریح نمایش داده می‌شود.</p>
    <form method='GET'>
        <input name='search' value='{{ request('search') }}' placeholder='جستجو'>
        <select name='group_id'>
            <option value=''>همه گروه‌ها</option>
            @foreach($groups as $group)
                <option value='{{ $group->id }}' @selected(request('group_id') == $group->id)>{{ $group->name }}</option>
            @endforeach
        </select>
        <button type='submit'>فیلتر</button>
    </form>
    <table>
        <thead><tr><th>نام</th><th>مالکیت</th><th>نسخه کاری</th><th>نسخه تأیید</th><th>نسخه انتشار</th><th>استقرارها</th></tr></thead>
        <tbody>
        @forelse($motions as $motion)
            <tr>
                <td>{{ $motion->name }}<small>{{ $motion->slug }}</small></td>
                <td>{{ $motion->ownership_type }}</td>
                <td>{{ $motion->latest_working_version }}</td>
                <td>{{ $motion->approved_version ?? '-' }}</td>
                <td>{{ $motion->published_version ?? '-' }}</td>
                <td>{{ $motion->deployments_count }}</td>
            </tr>
        @empty
            <tr><td colspan='6'>موردی وجود ندارد.</td></tr>
        @endforelse
        </tbody>
    </table>
    {{ $motions->links() }}
</div>
