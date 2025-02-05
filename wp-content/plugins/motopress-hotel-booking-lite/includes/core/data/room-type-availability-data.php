<?php

namespace MPHB\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Data transfer object for room type availability.
 */
class RoomTypeAvailabilityData extends AbstractDataTransferObject {

	/**
	 * @var \MPHB\Core\RoomTypeAvailabilityStatus constant
	 */
	private $roomTypeStatus;
	private $availableRoomsCount;
	private $isCheckInDate;
	private $isCheckOutDate;
	private $isStayInNotAllowed;
	private $isCheckInNotAllowed;
	private $isCheckOutNotAllowed;
	private $isEarlierThanMinAdvanceDate;
	private $isLaterThanMaxAdvanceDate;

	public function __construct(
		$roomTypeStatus,
		$availableRoomsCount = 0,
		$isCheckInDate = false,
		$isCheckOutDate = false,
		$isStayInNotAllowed = false,
		$isCheckInNotAllowed = false,
		$isCheckOutNotAllowed = false,
		$isEarlierThanMinAdvanceDate = false,
		$isLaterThanMaxAdvanceDate = false
	) {
		$this->roomTypeStatus              = $roomTypeStatus;
		$this->availableRoomsCount         = $availableRoomsCount;
		$this->isCheckInDate               = $isCheckInDate;
		$this->isCheckOutDate              = $isCheckOutDate;
		$this->isStayInNotAllowed          = $isStayInNotAllowed;
		$this->isCheckInNotAllowed         = $isCheckInNotAllowed;
		$this->isCheckOutNotAllowed        = $isCheckOutNotAllowed;
		$this->isEarlierThanMinAdvanceDate = $isEarlierThanMinAdvanceDate;
		$this->isLaterThanMaxAdvanceDate   = $isLaterThanMaxAdvanceDate;
	}

	public function getRoomTypeStatus() {
		return $this->roomTypeStatus;
	}

	public function getAvailableRoomsCount() {
		return $this->availableRoomsCount;
	}

	public function isCheckInDate() {
		return $this->isCheckInDate;
	}

	public function isCheckOutDate() {
		return $this->isCheckOutDate;
	}

	public function isStayInNotAllowed() {
		return $this->isStayInNotAllowed;
	}

	public function isCheckInNotAllowed() {
		return $this->isCheckInNotAllowed;
	}

	public function isCheckOutNotAllowed() {
		return $this->isCheckOutNotAllowed;
	}

	public function isEarlierThanMinAdvanceDate() {
		return $this->isEarlierThanMinAdvanceDate;
	}

	public function isLaterThanMaxAdvanceDate() {
		return $this->isLaterThanMaxAdvanceDate;
	}
}
