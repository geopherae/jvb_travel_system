<?php
function getStatusBadgeClass($status) {
  $map = [
    // 🟡 amber: Initial or waiting states
    'pending'         => 'bg-amber-100 text-amber-700',
    'submitted'       => 'bg-amber-100 text-amber-700',
    'awaiting docs'   => 'bg-amber-100 text-amber-700',

    // 🔵 Blue: In-progress or under evaluation
    'under review'    => 'bg-sky-100 text-sky-700',
    'trip ongoing'    => 'bg-sky-100 text-sky-700',

    // 🟢 Green: Success or completion
    'confirmed'       => 'bg-green-100 text-green-800',
    'approved'        => 'bg-green-100 text-green-800',
    'trip completed'  => 'bg-green-100 text-green-800',

    // 🔴 Red: Errors or required action
    'cancelled'       => 'bg-red-100 text-red-700',
    'resubmit files'  => 'bg-red-100 text-red-700',
    'rejected'        => 'bg-red-100 text-red-700',
    'not submitted'   => 'bg-gray-100 text-gray-600',
  ];

  $key = strtolower(trim($status ?? ''));
  return $map[$key] ?? 'bg-gray-100 text-gray-600';
}

function getStatusClass(string $status): string {
  return match ($status) {
    'Approved'      => 'bg-emerald-100 text-emerald-700 border border-emerald-300',
    'Rejected'      => 'bg-red-100 text-red-700 border border-red-300',
    'Pending', 
    'Submitted'     => 'bg-yellow-100 text-yellow-700 border border-yellow-300',
    'Not Submitted' => 'bg-gray-100 text-gray-600 border border-gray-300',
    default         => 'bg-yellow-100 text-yellow-700 border border-yellow-300'
  };
}

function getToastClass(string $type): string {
  return match ($type) {
    'success' => 'bg-green-100 text-green-800 border border-green-300',
    'error'   => 'bg-red-100 text-red-800 border border-red-300',
    'warning' => 'bg-yellow-100 text-yellow-800 border border-yellow-300',
    'info'    => 'bg-blue-100 text-blue-800 border border-blue-300',
    default   => 'bg-gray-100 text-gray-800 border border-gray-300'
  };
}