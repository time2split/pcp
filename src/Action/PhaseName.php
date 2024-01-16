<?php
namespace Time2Split\PCP\Action;

enum PhaseName
{

    case ReadingOneFile;

    case OpeningDirectory;

    case ProcessingFiles;
}