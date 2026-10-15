<s:reviews sort="newest" paginate="10" as="items">
    @foreach ($items as $review)
        <article id="review-{{ $review['id'] }}">
            <p aria-label="{{ $review['rating'] }} out of 5">@foreach ($review['stars'] as $star){{ $star['full'] ? '★' : '☆' }}@endforeach</p>
            @if ($review['title'])<h3>{!! $review['title'] !!}</h3>@endif
            <p>{!! $review['body'] !!}</p>
            <p>
                {!! $review['name'] !!}, {{ $review['date']->format('j F Y') }}
                @if ($review['verified'])· Verified purchase@endif
            </p>
            @foreach ($review['photos'] as $photo)
                <img src="{{ $photo['url'] }}" alt="{!! $photo['alt'] !!}" width="160">
            @endforeach
            @if ($review['has_reply'])
                <blockquote><strong>Our reply:</strong> {!! $review['reply'] !!}</blockquote>
            @endif
            <s:reviews:helpful :id="$review['id']"><button>Helpful ({{ $review['helpful'] }})</button></s:reviews:helpful>
        </article>
    @endforeach

    @if ($no_results)<p>Be the first to review this.</p>@endif

    @if ($paginate)
        @if ($paginate['prev_page'])<a href="{{ $paginate['prev_page'] }}">Newer</a>@endif
        @if ($paginate['next_page'])<a href="{{ $paginate['next_page'] }}">Older</a>@endif
    @endif
</s:reviews>
