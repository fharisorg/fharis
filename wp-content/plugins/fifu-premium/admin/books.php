<?php

class FifuBooks {

    function get_image_url($isbn) {
        for ($i = 1; $i <= 3; $i++) {
            $res = $this->get_image_url_book($isbn, $i);
            if ($res) {
                return $res['url'] ?? null;
            }
        }
        return null;
    }

    function get_image_url_book($isbn, $i) {
        $queryParams = http_build_query([
            'site' => fifu_get_home_url(),
            'partial_key' => fifu_partial_key(),
            'isbn' => $isbn,
        ]);
        $workerUrl = "https://find-image-book-{$i}.fifu.workers.dev?" . $queryParams;

        try {
            $response = wp_remote_get($workerUrl);

            if (is_wp_error($response) || wp_remote_retrieve_response_code($response) != 200)
                return null;

            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);

            if (!empty($data['url'] ?? ''))
                return $data;
        } catch (Exception $e) {
            error_log('fifu-books: ' . $e->getMessage() . ' - ISBN: ' . $isbn);
        }

        return null;
    }

}

function fifu_isbn_search($isbn) {
    $books = new FifuBooks();
    return $books->get_image_url($isbn);
}

