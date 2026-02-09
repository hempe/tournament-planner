<?php

namespace TP\Components;

enum Color: string
{
    case Primary = 'primary';
    case Accent = 'accent';
    case Light = 'light';
    case None = '';
}