<s:reviews:form redirect="#review">
    @if ($success)<p role="status">{!! $success !!}</p>@endif
    @if ($reason)
        <p>{!! $reason_message !!}</p>
    @else
        @if ($has_errors)
            <ul role="alert">@foreach ($error_list as $message)<li>{!! $message !!}</li>@endforeach</ul>
        @endif

        <fieldset>
            <legend>Your rating</legend>
            @foreach ([5, 4, 3, 2, 1] as $value)
                <label>
                    <input type="radio" name="rating" value="{{ $value }}" required @checked($old['rating'] == $value)>
                    {{ $value }} {{ $value == 1 ? 'star' : 'stars' }}
                </label>
            @endforeach
        </fieldset>

        @foreach ($criteria as $criterion)
            <label>{!! $criterion['label'] !!}
                <select name="{{ $criterion['name'] }}">
                    <option value="">–</option>
                    @foreach ([5, 4, 3, 2, 1] as $value)<option value="{{ $value }}" @selected($criterion['old'] == $value)>{{ $value }}</option>@endforeach
                </select>
            </label>
        @endforeach

        <label>Title <input name="title" value="{!! $old['title'] !!}" maxlength="{{ $title_limit }}"></label>
        <label>Your review <textarea name="body" maxlength="{{ $body_limit }}" required>{!! $old['body'] !!}</textarea></label>

        @if ($needs_name)
            <label>Your name <input name="name" value="{!! $old['name'] !!}" required></label>
            <label>Your email (not shown) <input type="email" name="email" value="{!! $old['email'] !!}" required></label>
        @endif

        @if ($photos_enabled)
            <label>Photos (up to {{ $max_photos }}) <input type="file" name="photos[]" accept="image/jpeg,image/png,image/webp" multiple></label>
        @endif

        <button type="submit">{{ $editing ? 'Update your review' : 'Send your review' }}</button>
    @endif
</s:reviews:form>
