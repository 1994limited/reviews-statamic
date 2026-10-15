<s:reviews:summary>
    @if ($has_reviews)
        <p>
            <span aria-hidden="true">@foreach ($stars as $star){{ $star['full'] ? '★' : ($star['half'] ? '⯪' : '☆') }}@endforeach</span>
            {{ $average }} out of 5, from {{ $count }} {{ $count == 1 ? 'review' : 'reviews' }}
        </p>
        <ul>
            @foreach ($breakdown as $row)
                <li>{{ $row['rating'] }} stars: {{ $row['total'] }} ({{ $row['percent'] }}%)</li>
            @endforeach
        </ul>
    @else
        <p>No reviews yet.</p>
    @endif
</s:reviews:summary>
