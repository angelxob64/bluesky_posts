<?php

namespace App\Http\Controllers;
use Illuminate\Http\JsonResponse;
use Revolution\Bluesky\Facades\Bluesky;

use Illuminate\Support\Facades\Log;

use Illuminate\Support\Facades\Http;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class PostController extends Controller
{
    
    //Funciones auxiliares para filtrar
    // Filtrar por palabra en el texto
    public static function byText(Collection $posts, string $keyword): Collection
    {
        return $posts->filter(function ($post) use ($keyword) {
            $text = data_get($post, 'record.text', '');
            return str_contains(strtolower($text), strtolower($keyword));
        });
    }

    // Filtrar por tags de menciones (si contiene alguna) (LOGICA: obtiene los handles de cada did de los posts)
    /*public static function byMentionTags(Collection $posts, array $allowedHandles): Collection
    {
        return $posts->filter(function ($post) use ($allowedHandles) {
            $facets = data_get($post, 'record.facets', []);
            $mentions = collect($facets)
                ->flatMap(fn($facet) => $facet['features'] ?? [])
                ->filter(fn($f) => $f['$type'] === 'app.bsky.richtext.facet#mention')
                ->pluck('did')
                ->map(function ($did) {
                    // Fetch handle from DID using the profile API
                    $response = Http::get('https://public.api.bsky.app/xrpc/app.bsky.actor.getProfile', [
                        'actor' => $did,
                    ]);

                    if ($response->successful()) {
                        return data_get($response->json(), 'handle');
                    }

                    return null;
                })
                ->filter()
                ->all();
            return !empty(array_intersect($mentions, $allowedHandles));
        });
    }*/

    public static function byMentionTags(Collection $posts, array $allowedHandles): Collection
    {
        
        //Cambio de logica para menor cantidad de llamadas en casos donde habran menos menciones que posts (caso comun)
        //Realizamos llamadas para obtener los DID de los handles
        $handleToDid = collect($allowedHandles)->mapWithKeys(function ($handle) {
            $cleanHandle = ltrim($handle, '@');

            $response = Http::get('https://public.api.bsky.app/xrpc/app.bsky.actor.getProfile', [
                'actor' => $cleanHandle,
            ]);

            if ($response->successful()) {
                return [$cleanHandle => data_get($response->json(), 'did')];
            }

            return [$cleanHandle => null];
        })->filter();

        $allowedDids = $handleToDid->values()->all();
        
    
        return $posts->filter(function ($post) use ($allowedDids) {
            $facets = data_get($post, 'record.facets', []);

            $mentionDids = collect($facets)
                ->flatMap(fn($facet) => $facet['features'] ?? [])
                ->filter(fn($f) => $f['$type'] === 'app.bsky.richtext.facet#mention')
                ->pluck('did')
                ->all();

            return !empty(array_intersect($mentionDids, $allowedDids));
        });
    }


    // Filtrar por tags (si contiene alguna)
    public static function byTags(Collection $posts, array $allowedTags): Collection
    {
        return $posts->filter(function ($post) use ($allowedTags) {
            $facets = data_get($post, 'record.facets', []);
            $tags = collect($facets)
                ->flatMap(fn($facet) => $facet['features'] ?? [])
                ->filter(fn($f) => $f['$type'] === 'app.bsky.richtext.facet#tag')
                ->pluck('tag')
                ->all();

            return !empty(array_intersect($tags, $allowedTags));
        });
    }


    //Funcion para realizar busqueda de posts con la palabra (SIN PAGINACION)
    /*public function search(Request $request): JsonResponse
    {
        $q = $request->query('q');
        $tags = $request->query('tags');
        $mentions = $request->query('mentions');

        if (!$q && !$tags && !$mentions) {
            return response()->json(['error' => 'Por lo menos un parametro es requerido'], 400);
        }

        //Transformo el string de tags. Hago un filtro para # y @, asi pueden ingresar #test,#test1 o test,test
        $allowedTags = collect(explode(',', $tags))
            ->map(fn($tag) => ltrim(trim($tag), '#@'))
            ->filter()
            ->values()
            ->all();

        //Transformo el string de menciones. Hago un filtro para # y @, asi pueden ingresar @test,@test1 o test,test
        $allowedMentions = collect(explode(',', $mentions))
            ->map(fn($mention) => ltrim(trim($mention), '#@'))
            ->filter()
            ->values()
            ->all();

        //Nuestra app permitira la busqueda sin texto a pesar que la api no lo permite
        //En ese caso usaremos el primer # como texto, o la primera mencion en caso de no tener tags
        //De este modo podremos buscar sin texto, sin embargo por simplicidad solo tendremos en cuenta el primer hash
        //De incluir varios, los resultados podrian estar filtrados por el orden de ellos segun lo maneje la api
        //Una solucion que podriamos implementar de necesitarse seria un loop con cada hash y realizar un llamado a la api por hash, luego mezclar las respuestas eliminando duplicados
        //En este caso evitaremos eso para reducir la complejidad y evitar varios calls a la api por llamada de la funcion
        if (!$q)
        {
            if (!empty($allowedTags)) {
                $firstTag = ltrim($allowedTags[0], '#@');
                $q = '#' . $firstTag;
            } elseif (!empty($allowedMentions)) {
                $firstMention = ltrim($allowedMentions[0], '#@');
                $q = '@' . $firstMention;
            }    
        }

        // Llamada api
        $response = Http::get('https://public.api.bsky.app/xrpc/app.bsky.feed.searchPosts', [
            'q' => $q ?: '',
            'limit' => 10,
        ]);

        $data = $response->json();

        //Log::debug('Bluesky API Response:', $data);

        $posts = collect($data['posts'] ?? []);

        // Filtrar por tags
        if (!empty($allowedTags)) {
            $posts = $this->byTags($posts, $allowedTags);
        }

        // Filtrar por menciones
        if (!empty($allowedMentions)) {
            $posts = $this->byMentionTags($posts, $allowedMentions);
        }
        Log::debug('Bluesky API Menciones:', $posts->toArray());

        // Datos que seran retornados
        $mapped = $posts->map(function (array $post) {
            return [
                'displayName' => data_get($post, 'author.displayName'),
                'handle'      => data_get($post, 'author.handle'),
                'did'         => data_get($post, 'author.did'),
                'avatar'      => data_get($post, 'author.avatar'),
                'text'        => data_get($post, 'record.text'),
                'date'        => data_get($post, 'record.createdAt'),
                'replyCount'  => data_get($post, 'replyCount'),
                'repostCount' => data_get($post, 'repostCount'),
                'likeCount'   => data_get($post, 'likeCount'),
                'quoteCount'  => data_get($post, 'quoteCount'),
            ];
        })->values();
        return response()->json($mapped);
    }*/

    public function search(Request $request): JsonResponse
    {
        $q = $request->query('q');
        $tags = $request->query('tags');
        $mentions = $request->query('mentions');
        $page = (int) $request->query('page', 0);
        $perPage = (int) $request->query('perPage', 10);
        $sort = $request->query('sort');

        if (!$q && !$tags && !$mentions) {
            return response()->json();
            //return response()->json(['error' => 'Por lo menos un parámetro es requerido'], 400);
        }

        $allowedTags = collect(explode(',', $tags))
            ->map(fn($tag) => ltrim(trim($tag), '#@'))
            ->filter()
            ->values()
            ->all();

        $allowedMentions = collect(explode(',', $mentions))
            ->map(fn($mention) => ltrim(trim($mention), '#@'))
            ->filter()
            ->values()
            ->all();


        //Nuestra app permitira la busqueda sin texto a pesar que la api no lo permite
        //En ese caso usaremos el primer # como texto, o la primera mencion en caso de no tener tags
        //De este modo podremos buscar sin texto, sin embargo por simplicidad solo tendremos en cuenta el primer hash
        //De incluir varios, los resultados podrian estar filtrados por el orden de ellos segun lo maneje la api
        //Una solucion que podriamos implementar de necesitarse seria un loop con cada hash y realizar un llamado a la api por hash, luego mezclar las respuestas eliminando duplicados
        //En este caso evitaremos eso para reducir la complejidad y evitar varios calls a la api por llamada de la funcion
        if (!$q) {
            if (!empty($allowedTags)) {
                $q = '#' . $allowedTags[0];
            } elseif (!empty($allowedMentions)) {
                $q = '@' . $allowedMentions[0];
            }
        }

        $response = Http::get('https://public.api.bsky.app/xrpc/app.bsky.feed.searchPosts', [
            'q' => $q ?: '',
            'limit' => 100,
        ]);

        $data = $response->json();
        $posts = collect($data['posts'] ?? []);

        if (!empty($allowedTags)) {
            $posts = $this->byTags($posts, $allowedTags);
        }

        if (!empty($allowedMentions)) {
            $posts = $this->byMentionTags($posts, $allowedMentions);
        }

        // Aplicamos el sort que recibimos del front
        if ($sort) {
            switch ($sort) {
                case 'createdAt':
                    $posts = $posts->sortByDesc(fn($p) => data_get($p, 'record.createdAt'));
                    break;
                case 'createdAtAsc':
                    $posts = $posts->sortBy(fn($p) => data_get($p, 'record.createdAt'));
                    break;
                case 'replyCount':
                    $posts = $posts->sortByDesc('replyCount');
                    break;
                case 'repostCount':
                    $posts = $posts->sortByDesc('repostCount');
                    break;
                case 'likeCount':
                    $posts = $posts->sortByDesc('likeCount');
                    break;
                case 'quoteCount':
                    $posts = $posts->sortByDesc('quoteCount');
                    break;
            }
            $posts = $posts->values();
        }

        $paginated = $posts->slice($page * $perPage, $perPage)->values();

        $mapped = $paginated->map(function (array $post) {
            return [
                'displayName' => data_get($post, 'author.displayName'),
                'handle'      => data_get($post, 'author.handle'),
                'did'         => data_get($post, 'author.did'),
                'avatar'      => data_get($post, 'author.avatar'),
                'text'        => data_get($post, 'record.text'),
                'date'        => data_get($post, 'record.createdAt'),
                'replyCount'  => data_get($post, 'replyCount'),
                'repostCount' => data_get($post, 'repostCount'),
                'likeCount'   => data_get($post, 'likeCount'),
                'quoteCount'  => data_get($post, 'quoteCount'),
            ];
        });

        return response()->json([
            'posts' => $mapped,
            'hasMore' => ($page + 1) * $perPage < $posts->count(),
        ]);
    }



}
