<?php

use App\Support\NewsHtml;

// Treść newsa z edytora: biała lista znaczników, linki social, obrazki tylko z storage/news.

test('scripts and unknown attributes are removed', function () {
    expect(NewsHtml::clean('<p onclick="x()">Lech <strong>gola</strong><script>alert(1)</script></p>'))
        ->toBe('<p>Lech <strong>gola</strong></p>');
});

test('unknown tags keep their text', function () {
    expect(NewsHtml::clean('<div><span style="color:red">tekst</span></div>'))->toBe('tekst');
});

test('dangerous links keep only their text', function () {
    expect(NewsHtml::clean('<p><a href="javascript:alert(1)">zły <i>link</i></a></p>'))->toBe('<p>zły <i>link</i></p>');
});

test('social links keep the icon class and open in a new tab', function () {
    expect(NewsHtml::clean('<a href="https://facebook.com/lech" class="social-link social-facebook">FB</a><a href="https://a.pl" class="social-evil">A</a>'))
        ->toBe('<a href="https://facebook.com/lech" target="_blank" rel="noopener nofollow" class="social-link social-facebook">FB</a>'
            .'<a href="https://a.pl" target="_blank" rel="noopener nofollow">A</a>');
});

test('only uploaded images stay with an allowed width', function () {
    $html = NewsHtml::clean('<p><img src="https://lechtyper.pl/storage/news/a_1.jpg" data-width="40" onerror="x()">'
        .'<img src="https://evil.com/x.jpg"><img src="javascript:alert(1)//storage/news/a.jpg"></p>');

    expect($html)->toBe('<p><img src="https://lechtyper.pl/storage/news/a_1.jpg" alt="" data-width="100" data-align="center" style="width: 100%" class="news-img news-img-center" loading="lazy"></p>');
});

test('old plain text news become paragraphs', function () {
    expect(NewsHtml::render("Raz\ndwa\n\n<b>Trzy</b>"))->toBe("<p>Raz<br>\ndwa</p><p>&lt;b&gt;Trzy&lt;/b&gt;</p>")
        ->and(NewsHtml::plain('<p>Raz <b>dwa</b></p><p>Trzy &amp; cztery</p>'))->toBe("Raz dwa\nTrzy & cztery");
});
