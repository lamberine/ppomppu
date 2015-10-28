<?php
/**
 * 뽐뿌 댓글 삭제 툴
 * 
 * @see 사용방법
 * 
 * 1. 크롬 브라우저로 뽐뿌에 로그인
 * 2. 개발자 도구를 오픈(윈도우 단축키 F12, 맥 단축키 Option+Command+I)
 * 3. Resources 탭으로 이동
 * 4. Cookies 항목을 선택
 * 5. ppomppu.co.kr 항목을 선택
 * 6. PHPSESSID의 Value 값을 복사하여 $phpsessid 변수에 붙여넣기
 * 7. 현재 보고 있는 파일을 브라우저나 콘솔을 통해 구동
 */
class delPpomppu {
    //PHP 세션 ID를 이곳에 기재
    private $phpsessid = '';

    private $myCommentUrl = 'http://www.ppomppu.co.kr/myinfo/member_my_comment_list.php';
    private $boardBaseUrl = 'http://www.ppomppu.co.kr/zboard';
    private $articleUrlRegexr = '@(http://www.ppomppu.co.kr/zboard/view.php\?id=[\w]+&no=[\d]+)">@';
    private $displayCountPerPage = 20;
    private $totalCommentCountRegexr = '/내가 쓴 코멘트 리스트 \( 총 ([\d]+) 건 \)/';
    
    public function __construct() {
        $this->curl = new curl;
        $this->curl->setCookie('PHPSESSID=' . $this->phpsessid);  
    }
    
    public function main() {
        $page = 1;
        $maxPage = 1;

        while ($page <= $maxPage) {        
            $myCommentHtml = $this->curl->get($this->myCommentUrl . '?page=' . $page);
            $myCommentHtml = mb_convert_encoding($myCommentHtml, 'UTF-8', 'EUC-KR');

            if ($page == 1) {
                preg_match($this->totalCommentCountRegexr, $myCommentHtml, $totalCommentCount);
                $maxPage = ceil($totalCommentCount[1] / $this->displayCountPerPage);
            }
            
            preg_match_all($this->articleUrlRegexr, $myCommentHtml, $articles);
            
            foreach (array_unique($articles[1]) as $articleUrl) {
                $articleHtml = $this->curl->get($articleUrl);
    
                preg_match_all('@del_comment_ok.php\?id=([\w]+)&no=([\d]+)&c_no=([\d]+)@', $articleHtml, $delLinks);

                foreach ($delLinks[0] as $idx => $delLink) {
                    //지우면 안되는 댓글은 여기에 글 번호를 나열
                    if (in_array($delLinks[2][$idx], [0, 0])) {
                        continue;
                    }

                    $this->curl->setReferer($articleUrl);

                    $this->curl->post('http://www.ppomppu.co.kr/zboard/vote_ex.php', [
                        'id' => $delLinks[1][$idx],
                        'no' => $delLinks[2][$idx],
                        'c_no' => $delLinks[3][$idx],
                        'memo' => '.'
                    ]);
                                        
                    $this->curl->get($this->boardBaseUrl . '/' . $delLink);
                    
                    echo $this->boardBaseUrl . '/' . $delLink . "\n";
                }
            }

            $page++;
        }
    }
}

class curl
{
    private $ch;
    private $header = array();

    function __construct()
    {
        $this->ch = curl_init();
        curl_setopt($this->ch, CURLOPT_RETURNTRANSFER, TRUE);
    }

    function __destruct()
    {
        curl_close($this->ch);
    }

    /**
     * GET request
     */
    public function get($url, $params = array())
    {
        curl_setopt($this->ch, CURLOPT_HTTPGET, true);

        if (!empty($params)) {
            $url .=  '?' . http_build_query($params);
        }

        return $this->request($url);
    }

    /**
     * POST request
     */
    public function post($url, $params = array())
    {
        curl_setopt($this->ch, CURLOPT_POST, true);

        return $this->request($url, $params);
    }

    /**
     * request
     *
     * @return  response
     */
    private function request($url, $params = array())
    {
        curl_setopt($this->ch, CURLOPT_URL, $url);

        if (!empty($this->header)) {
            curl_setopt($this->ch, CURLOPT_HTTPHEADER, $this->getHeader());
        }

        if (!empty($params)) {
            if (isset($this->header['Content-type']) && $this->header['Content-type'] == 'application/json') {
                curl_setopt($this->ch, CURLOPT_POSTFIELDS, json_encode($params));
            }
            else {
                curl_setopt($this->ch, CURLOPT_POSTFIELDS, http_build_query($params));
            }
        }

        $response = curl_exec($this->ch);

        return $response;
    }

    private function getHeader() {
        $header = array();
        foreach ($this->header as $key => $value) {
            $header[] = $key . ': ' . $value;
        }

        return $header;
    }

    /**
     * 레퍼러 설정
     */
    public function setReferer($url)
    {
        curl_setopt($this->ch, CURLOPT_REFERER, $url);
    }

    /**
     * 쿠키 설정
     */
    public function setCookie($cookie) {
        curl_setopt($this->ch, CURLOPT_COOKIE, $cookie);
    }
}

$delPpomppu = new delPpomppu();
$delPpomppu->main();