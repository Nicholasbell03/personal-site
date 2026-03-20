{!! '<?xml version="1.0" encoding="UTF-8"?>' !!}
<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">
    <channel>
        <title>Nicholas Bell</title>
        <link>{{ $frontendUrl }}</link>
        <description>Software engineer building modern web applications. Blog posts, projects, and curated links.</description>
        <language>en</language>
        <atom:link href="{{ $frontendUrl }}/feed" rel="self" type="application/rss+xml" />
        @foreach ($items as $item)
        <item>
            <title>{!! htmlspecialchars($item['title'], ENT_XML1, 'UTF-8') !!}</title>
            <link>{{ $item['link'] }}</link>
            <description>{!! htmlspecialchars($item['description'], ENT_XML1, 'UTF-8') !!}</description>
            <pubDate>{{ $item['pubDate'] }}</pubDate>
            <guid isPermaLink="true">{{ $item['link'] }}</guid>
            <category>{{ $item['category'] }}</category>
            @if ($item['imageUrl'])
            <enclosure url="{{ $item['imageUrl'] }}" length="0" type="{{ $item['imageType'] }}" />
            @endif
        </item>
        @endforeach
    </channel>
</rss>
