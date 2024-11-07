	private ?array ${{to_name}}s = null;

	/**
	 * Save {{to}} list
	 *
	 * @param array ${{to_name}}s {{to}} list
	 *
	 * @return void
	 */
	public function set{{to}}s(array ${{to_name}}s): void {
		$this->{{to_name}}s = ${{to_name}}s;
	}

	/**
	 * Get {{to}} list
	 *
	 * @return array {{to_name}} list
	 */
	public function get{{to}}s(): array {
		if (is_null($this->{{to_name}}s)) {
			$this->load{{to}}s();
		}
		return $this->{{to_name}}s;
	}

	/**
	 * Load {{to}} list
	 *
	 * @return void
	 */
	private function load{{to}}s(): void {
		$this->{{to_name}}s = {{to}}::where(['{{to_field}}' => $this->{{from_field}}]);
	}
