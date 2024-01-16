<?php
namespace Time2Split\PCP\Action;

enum PhaseState
{

    case Start;

    case Run;

    case Stop;
}