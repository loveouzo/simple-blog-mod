<?php
global $Wcms;

class SimpleBlog {
	public $slug = 'blog';

	private $Wcms;

	private $db;

	private $dbPath;

	private $dateFormat = 'd F Y';

	private $path = [''];

	private $active = false;

	public function __construct($load) {
		global $Wcms;
		$this->dbPath = $Wcms->dataPath . '/simpleblog.json';
		if ($load) {
			$this->Wcms =&$Wcms;
		}
	}

	public function init(): void {
		$this->db = $this->getDb();
	}

	private function getDb(): stdClass {
		if (! file_exists($this->dbPath)) {
			file_put_contents($this->dbPath, json_encode([
				'title' => 'Blog',
				'posts' => [
					'hello-world' => [
						'title' => 'Hello, World!',
						'description' => 'This blog post and the first paragraph is the short snippet.',
						'date' => time(),
						'body' => "This is the full blog post content. Here's some more example text. Consectetur adipisicing elit. Quidem nesciunt voluptas tempore vero, porro reprehenderit cum provident eum sapiente voluptate veritatis, iure libero, fugiat iste soluta repellendus aliquid impedit alias.",
					],
				],
			], JSON_FORCE_OBJECT | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
		}

		return json_decode(file_get_contents($this->dbPath));
	}

	public function attach(): void {
		$this->Wcms->addListener('menu', [$this, 'menuListener']);
		$this->Wcms->addListener('page', [$this, 'pageListener']);
		$this->Wcms->addListener('css', [$this, 'startListener']);
		$this->Wcms->addListener('js', [$this, 'jsListener']);
		$this->Wcms->addListener('settings', [$this, 'alterAdmin']);

		$pathTest = $this->Wcms->currentPageTree;
		if (array_shift($pathTest) === $this->slug) {
			$headerResponse = 'HTTP/1.0 200 OK';
			$currentPageExists = true;

			if ($pathTest) {
				$path = implode('-', $pathTest);
				if (! property_exists($this->db->posts, $path)) {
					$headerResponse = 'HTTP/1.0 404 Not Found';
					$currentPageExists = false;
				}
			}
			global $Wcms;
			$Wcms->headerResponseDefault = false;
			$Wcms->headerResponse = $headerResponse;
			$Wcms->currentPageExists = $currentPageExists;
		}
	}

	private function save(): void {
		file_put_contents($this->dbPath,
			json_encode($this->db, JSON_FORCE_OBJECT | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
	}

	public function set(): void {
		$numArgs = func_num_args();
		$args = func_get_args();

		switch ($numArgs) {
			case 2:
				$this->db->{$args[0]} = $args[1];
				break;
			case 3:
				$this->db->{$args[0]}->{$args[1]} = $args[2];
				break;
			case 4:
				$this->db->{$args[0]}->{$args[1]}->{$args[2]} = $args[3];
				break;
			case 5:
				$this->db->{$args[0]}->{$args[1]}->{$args[2]}->{$args[3]} = $args[4];
				break;
		}
		$this->save();
	}

	public function get() {
		$numArgs = func_num_args();
		$args = func_get_args();
		switch ($numArgs) {
			case 1:
				return $this->db->{$args[0]};
			case 2:
				return $this->db->{$args[0]}->{$args[1]};
			case 3:
				return $this->db->{$args[0]}->{$args[1]}->{$args[2]};
			case 4:
				return $this->db->{$args[0]}->{$args[1]}->{$args[2]}->{$args[3]};
			case 5:
				return $this->db->{$args[0]}->{$args[1]}->{$args[2]}->{$args[3]}->{$args[4]};
		}
	}

	public function startListener(array $args): array {
		// This code redides here instead of in init() because currentPage is empty there.
		// This is the first location where currentPage is set
		$path = $this->Wcms->currentPageTree;
		if (array_shift($path) === $this->slug) {
			$this->active = true;
			$this->path = $path ? implode('-', $path) : [''];
		}

		if ($this->active) {
			// Remove page doesn't exist notice on blog pages
			if (isset($_SESSION['alert']['info'])) {
				foreach ($_SESSION['alert']['info'] as $i => $v) {
					if (strpos($v['message'], 'This page ') !== false && strpos($v['message'], ' doesn\'t exist.</b> Click inside the content below to create it.') !== false) {
						unset($_SESSION['alert']['info'][$i]);
					}
				}
			}
		}
		// built-in CSS or theme CSS
		if (isset ($this->Wcms->get('config')->blogCSS) && $this->Wcms->get('config')->blogCSS == "theme") {
			$args[0] .= ""; // do not embed css-link
		} else {
			$args[0] .= "<link rel='stylesheet' href='{$this->Wcms->url('plugins/simple-blog/css/blog.css')}'>"; // embed built-in css
		}

		return $args;
	}

	public function jsListener(array $args): array {
		if (! $this->active) {
			return $args;
		}
		if ($this->Wcms->loggedIn) {

			$args[0] .= '<script src="https://code.jquery.com/jquery-1.12.4.min.js" integrity="sha384-nvAa0+6Qg9clwYCGGPpDQLVpLNn0fRaROjHqs13t4Ggj3Ez50XnGQqc/r8MhnRDZ" crossorigin="anonymous"></script>';
			$args[0] .= "<script src='{$this->Wcms->url('plugins/simple-blog/js/blog.js')}'></script>";
		}

		return $args;
	}

	public function menuListener(array $args): array {
		// Add blog menu item
		$extra = $this->active ? 'active ' : '';

		$args[0] .= <<<HTML
        <li class="{$extra}nav-item">
            <a class="nav-link" href="{$this->Wcms->url($this->slug)}">Blog</a>
        </li>
HTML;

		return $args;
	}

	public function pageListener(array $args): array {
		$args = $this->setMetaTags($args);

		if ($this->active) {
			switch ($this->path[0]) {
				case '':
					// Start rendering homepage
					$args[0] = '';

					if ($this->Wcms->loggedIn) {
						$args[0] = "<div class='text-right'><a href='#' class='btn btn-light' onclick='blog.new(); return false;'><span class='glyphicon glyphicon-plus-sign'></span> Create new post</a></div>";
					}

					$args[0] .= <<<HTML
HTML;
					// get the Intro Template
					$introTemplate = $this->Wcms->get('config')->blogIntroTextTemplate;
					// get the read more text
					$readMore = $this->Wcms->get('config')->blogReadMore;
					// Little inline reversing
					foreach (array_reverse((array) $this->db->posts, true) as $slug => $post) {
						$date = date($this->dateFormat, $post->date);
						$currentPostURL = $this->Wcms->url($this->slug . '/' . $slug);
						// prepare the placeholder-array
						$placeholder = ["[[POST-URL]]", "[[POST-TITLE]]", "[[DATE]]", "[[POST-DESCRIPTION]]", "[[MORE-TEXT]]"];
						// prepare the wcms variables
						$variables = [$currentPostURL, $post->title, $date, $post->description, $readMore];
						// get the template and replace everything
						$currentIntro = str_replace($placeholder, $variables, $introTemplate);
						$args[0] .= <<<HTML
                        {$currentIntro}
HTML;
						/*$args[0] .= <<<HTML
                        <div class="post card">
                            <h3><a href="{$this->Wcms->url($this->slug . '/' . $slug)}" class="text-right">{$post->title}</a></h3>
                            <div class="meta">
                                <div class="row">
                                    <div class="col-sm-12 text-right"><small>{$date}</small></div>
                                </div>
                            </div>
                            <p class="description">{$post->description}</p>
                            <a href="{$this->Wcms->url($this->slug . '/' . $slug)}" class="text-right">&#8618; Read more</a>
                        </div>
HTML;*/
					}
					break;
				default:
					if (isset($this->db->posts->{$this->path})) {
						// Display post
						$post = $this->db->posts->{$this->path};
						$date = date($this->dateFormat, $post->date);

						// get the back-text
						$goBackText = $this->Wcms->get('config')->blogBack;

						$edit = '';
						$description = '';
						$delete = '';
						if ($this->Wcms->loggedIn) {
							$args[0] = <<<HTML
                            <div class="post">
                                <div data-target="blog" style='margin-top:0;' id="title" class="title editText editable">{$post->title}</div>
                                <p class="meta">{$date} &nbsp; &bull; &nbsp; <a href='{$this->Wcms->url('plugins/simple-blog/delete.php')}?page={$this->path}&token={$this->Wcms->getToken()}' onclick='return confirm(\"Are you sure you want to delete this post?\")'>Delete</a></p>
                                <hr>
                                <div data-target="blog" id="description" class='meta editText editable'>{$post->description}</div>
                                <hr>
                                <div data-target="blog" id="body" class="body editText editable">{$post->body}</div>
                            </div>
							<div class="text-left">
								<br /><br />
								<a href="../$this->slug" class="btn btn-sm btn-light"><span class="glyphicon glyphicon-chevron-left small"></span>{$goBackText}</a>
							</div>
HTML;
						} else {
							// get the Post Template
							$postTemplate = $this->Wcms->get('config')->blogPostTextTemplate;

							// prepare the placeholder-array
							$placeholder = ["[[POST-TITLE]]", "[[DATE]]", "[[POST-BODY]]", "[[BACK-LINK]]", "[[BACK-TEXT]]"];
							// prepare the wcms variables
							$variables = [$post->title, $date, $post->body, "../".$this->slug, $goBackText];
							// get the template and replace everything
							$currentPost = str_replace($placeholder, $variables, $postTemplate);
							$args[0] = <<<HTML
                            {$currentPost}
HTML;
							/*$args[0] = <<<HTML
                            <div class="post">
                                <h1 class="title">{$post->title}</h1>
                                <p class="meta">{$date}</p>
                                <div class="body">{$post->body}</div>
                            </div>
HTML;*/
						}

						/*$args[0] .= <<<HTML
                        
                        <div class="text-left">
                            <br /><br />
                            <a href="../$this->slug" class="btn btn-sm btn-light"><span class="glyphicon glyphicon-chevron-left small"></span> Back to all blog posts</a>
                        </div>
HTML;*/
					} else {
						// Display 404 (unless it's admin, then it's never a 404)
						$args[0] = $this->Wcms->get('pages', '404')->content;
					}
					break;
			}
		}

		return $args;
	}

	private function setMetaTags(array $args): array {
		$subPage = strtolower($this->Wcms->currentPage);
		if ((($subPage !== $this->slug && isset($this->db->posts->{$subPage})) || $subPage === $this->slug)
			&& isset($args[1])
			&& ($args[1] === 'title' || $args[1] === 'description' || $args[1] === 'keywords')
		) {
			$args[0] = isset($this->db->posts->{$subPage})
				? $this->db->posts->{$subPage}->{$args[1] === 'keywords' ? 'description' : $args[1]}
				: $this->db->title;
			$length = strrpos(strip_tags($args[0]), ' ');
			$content = strip_tags($args[0]);
			if ($args[1] === 'title') {
				$args[0] = $length > 60 ? substr($content, 0, 57) . "..." : $content;
			} elseif ($args[1] === 'keywords') {
				$args[0] = str_replace(' ', ', ', $content);
			} elseif ($args[1] === 'description') {
				$args[0] = $content;
			}
		}

		return $args;
	}

	// admin veraendern
	public function alterAdmin(array $args): array {
		// defaults
		$defMoreText = "Read more";
		$defBackText = "Zurück zur Übersicht";
		$defIntroTemplate = '<div class="post card">
                            <h3><a href="[[POST-URL]]" class="text-right">[[POST-TITLE]]</a></h3>
                            <div class="meta">
                                <div class="row">
                                    <div class="col-sm-12 text-right"><small>[[DATE]]</small></div>
                                </div>
                            </div>
                            <p class="description">[[POST-DESCRIPTION]]</p>
                            <a href="[[POST-URL]]" class="text-right">[[MORE-TEXT]]</a>
                        </div>';
		$defPostTemplate = '<div class="post">
                                <h1 class="title">[[POST-TITLE]]</h1>
                                <p class="meta">[[DATE]]</p>
                                <div class="body">[[POST-BODY]]</div>
                            </div>
								<div class="text-left">
									<br /><br />
									<a href="[[BACK-LINK]]" class="btn btn-sm btn-light"><span class="glyphicon glyphicon-chevron-left small"></span>[[BACK-TEXT]]</a>
							</div>';

		// create new DOMdocument
        $doc = new DOMDocument();
        @$doc->loadHTML($args[0]);
        @$doc->loadHTML(mb_convert_encoding($args[0], 'HTML-ENTITIES', 'UTF-8'));

        $menuItem = $doc->createElement('li');
        $menuItem->setAttribute('class', 'nav-item');
        $menuItemA = $doc->createElement('a');
        $menuItemA->setAttribute('href', '#blog');
        $menuItemA->setAttribute('aria-controls', 'blog');
        $menuItemA->setAttribute('role', 'tab');
        $menuItemA->setAttribute('data-toggle', 'tab');
        $menuItemA->setAttribute('class', 'nav-link');
        $menuItemA->nodeValue = 'Blog';
        $menuItem->appendChild($menuItemA);

        $doc->getElementById('currentPage')->parentNode->parentNode->childNodes->item(1)->appendChild($menuItem);

        $wrapper = $doc->createElement('div');
        $wrapper->setAttribute('role', 'tabpanel');
        $wrapper->setAttribute('class', 'tab-pane');
        $wrapper->setAttribute('id', 'blog');

        // Contents of wrapper

		// read more text
		$label = $doc->createElement("p");
		$label->setAttribute("class", "subTitle");
		$label->nodeValue = '"Weiter lesen ..."-Text';
		$wrapper->appendChild($label);

		$wrapper2 = $doc->createElement("div");
		$wrapper2->setAttribute("class", "change");

		$input = $doc->createElement("div");
		$input->setAttribute("class", "editText");
		$input->setAttribute("data-target", "config");
		$input->setAttribute("id", "blogReadMore");
		// JUST AS A REMINDER how to get the data
		// echo $this->Wcms->get('config')->blogReadMore;

		if (isset ($this->Wcms->get('config')->blogReadMore)) {
			$moreText = $this->Wcms->get('config')->blogReadMore;
		} else {
			// otherwise take the defaults from above
			$moreText = $defMoreText;
		}
		$input->nodeValue = $moreText;

		$wrapper2->appendChild($input);
        $wrapper->appendChild($wrapper2);

		// back to all posts
		$label = $doc->createElement("p");
		$label->setAttribute("class", "subTitle");
		$label->nodeValue = '"Zurück zur Übersicht"-Text';
		$wrapper->appendChild($label);

		$wrapper2 = $doc->createElement("div");
		$wrapper2->setAttribute("class", "change");

		$input = $doc->createElement("div");
		$input->setAttribute("class", "editText");
		$input->setAttribute("data-target", "config");
		$input->setAttribute("id", "blogBack");

		if (isset ($this->Wcms->get('config')->blogBack)) {
			$backText = $this->Wcms->get('config')->blogBack;
		} else {
			// otherwise take the defaults from above
			$backText = $defBackText;
		}
		$input->nodeValue = $backText;

		$wrapper2->appendChild($input);
        $wrapper->appendChild($wrapper2);

		// intro text template
		$label = $doc->createElement("p");
		$label->setAttribute("class", "subTitle");
		$label->nodeValue = 'Template Einleitungstext';
		$wrapper->appendChild($label);

		$hints = $doc->createElement("p");
		$hints->setAttribute("class", "subTitle");
		$hints->setAttribute("class", "small");
		$hints->nodeValue = 'Platzhalter: [[POST-URL]], [[POST-TITLE]], [[DATE]], [[POST-DESCRIPTION]], [[MORE-TEXT]]';
		$wrapper->appendChild($hints);

		$wrapper2 = $doc->createElement("div");
		$wrapper2->setAttribute("class", "change");

		$input = $doc->createElement("div");
		$input->setAttribute("class", "editText");
		$input->setAttribute("data-target", "config");
		$input->setAttribute("id", "blogIntroTextTemplate");
		if (isset ($this->Wcms->get('config')->blogIntroTextTemplate)) {
			$introTemplate = $this->Wcms->get('config')->blogIntroTextTemplate;
		} else {
			// otherwise take the defaults from above
			$introTemplate = $defIntroTemplate;
		}
		$input->nodeValue = $introTemplate;

		$wrapper2->appendChild($input);
        $wrapper->appendChild($wrapper2);

		// full text template
		$label = $doc->createElement("p");
		$label->setAttribute("class", "subTitle");
		$label->nodeValue = 'Template Artikel/Post';
		$wrapper->appendChild($label);

		$hints = $doc->createElement("p");
		$hints->setAttribute("class", "subTitle");
		$hints->setAttribute("class", "small");
		$hints->nodeValue = 'Platzhalter: [[POST-TITLE]], [[DATE]], [[POST-BODY]], [[BACK-LINK]], [[BACK-TEXT]]';
		$wrapper->appendChild($hints);

		$wrapper2 = $doc->createElement("div");
		$wrapper2->setAttribute("class", "change");

		$input = $doc->createElement("div");
		$input->setAttribute("class", "editText");
		$input->setAttribute("data-target", "config");
		$input->setAttribute("id", "blogPostTextTemplate");
		if (isset ($this->Wcms->get('config')->blogPostTextTemplate)) {
			$postTemplate = $this->Wcms->get('config')->blogPostTextTemplate;
		} else {
			// otherwise take the defaults from above
			$postTemplate = $defPostTemplate;
		}
		$input->nodeValue = $postTemplate;

		$wrapper2->appendChild($input);
        $wrapper->appendChild($wrapper2);

		// theme css or built-in css
		$label = $doc->createElement("p");
		$label->setAttribute("class", "subTitle");
		$label->nodeValue = "CSS auswählen";
		$wrapper->appendChild($label);

		$wrapper2 = $doc->createElement("div");
		$wrapper2->setAttribute("class", "change");

		$input = $doc->createElement("select");
		$input->setAttribute("id", "selectBlogCSS");
		$input->setAttribute("class", "wform-control");
		$input->setAttribute("onchange", "wcmsAdminActions.sendPostRequest('blogCSS',this.value,'config');");
		$input->setAttribute("name", "blogCSS");

		//use built-in CSS (css-file provided by plugin)
		$option = $doc->createElement("option");
		$option->setAttribute("value", "");
		$option->nodeValue = "Built-in CSS";
		$input->appendChild($option);

		// use theme CSS (declare your CSS in your themes' css file)
		$option = $doc->createElement("option");
		$option->setAttribute("value", "theme");
		$option->nodeValue = "Theme CSS";
		// get current value of select
		if (isset ($this->Wcms->get('config')->blogCSS) && $this->Wcms->get('config')->blogCSS == "theme")
			$option->setAttribute("selected", "selected");
		$input->appendChild($option);

		$wrapper2->appendChild($input);
		$wrapper->appendChild($wrapper2);

        // End of contents of wrapper

        $doc->getElementById('currentPage')->parentNode->appendChild($wrapper);

        $args[0] = preg_replace('~<(?:!DOCTYPE|/?(?:html|body))[^>]*>\s*~i', '', $doc->saveHTML());

        return $args;
    }
}
