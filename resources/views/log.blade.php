<!DOCTYPE html>
<html lang="en">

<head>
    <title>logxpro</title>
    <!-- Required meta tags -->
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no"/>

    <!-- Bootstrap CSS v5.2.0 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.0-beta1/dist/css/bootstrap.min.css" rel="stylesheet"
          integrity="sha384-0evHe/X+R7YkIZDRvuzKMRqM+OrBnVFBL6DOitfPri4tjfHxaWutUpFmBp4vmVor" crossorigin="anonymous"/>
</head>

<body>
<div class="logxpro container">
    <div class="title text-center mt-3 mb-5">
        <h1>logxpro</h1>
    </div>


    <div class="upload-file mb-4">
        <form action="{{ route('log.upload') }}" method="POST" enctype="multipart/form-data">
            @csrf

            @isset($file)
                <div class="form-group">
                    <span>File name: {{ $file->name }}</span>
                </div>

                <div class="form-group mb-2">
                    <span>File size: {{ $file->size }}</span>
                </div>
            @endisset

            <div class="row">
                <div class="col-md-4">
                    <input type="file" name="file" class="form-control">
                </div>

                <div class="col-md-6">
                    <button type="submit" class="btn btn-success">Upload</button>
                </div>
            </div>
        </form>
    </div>

    @isset($data)
        <div class="show-data">
            <div class="form-group">
                <h5>Request statistics:</h5>
            </div>
            <ul class="list-group list-group-flush mb-4">
                <li class="list-group-item">Requests: {{ number_format($data->requests , 0, ',', '.') }}</li>
                <li class="list-group-item">Request
                    success: {{ number_format($data->request_success , 0, ',', '.') }}</li>
                <li class="list-group-item">Request
                    errors: {{ number_format($data->request_errors , 0, ',', '.') }}</li>
            </ul>

            <div class="form-group">
                <h5>Request statistics users:</h5>
            </div>
            <div class="accordion accordion-flush" id="accordionFlushExample">
                @foreach($data->request_users as $key => $user)
                    <div class="accordion-item">
                        <h2 class="accordion-header" id="flush-heading-{{ $key }}">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                                    data-bs-target="#flush-collapse-{{ $key }}" aria-expanded="false"
                                    aria-controls="flush-collapse-{{ $key }}">
                                <span class="badge bg-primary">{{ $user->all }}</span>&nbsp;
                                <span class="badge bg-success">{{ $user->success }}</span>&nbsp;
                                <span class="badge bg-danger">{{ $user->errors }}</span>&ensp;
                                <span>{{ $key }}</span>
                            </button>
                        </h2>
                        <div id="flush-collapse-{{ $key }}" class="accordion-collapse collapse"
                             aria-labelledby="flush-heading-{{ $key }}" data-bs-parent="#accordionFlushExample">
                            <div class="accordion-body">
                                <b>{{ $key }}</b> <br>
                                Requests&emsp;&emsp;&emsp;&emsp;: <code>{{ $user->all }}</code> <br>
                                Request success&ensp;: <code>{{ $user->success }}</code> <br>
                                Request errors&emsp;&ensp;: <code>{{ $user->errors }}</code>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

        </div>
    @endisset

</div>
<!-- Bootstrap JavaScript Libraries -->
<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.9.2/dist/umd/popper.min.js"
        integrity="sha384-IQsoLXl5PILFhosVNubq5LC7Qb9DXgDA9i+tQ8Zj3iwWAwPtgFTxbJ8NT4GN1R8p"
        crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.0-beta1/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-pprn3073KE6tl6bjs2QrFaJGz5/SUsLqktiwsUTF55Jfv3qYSDhgCecCxMW52nD2"
        crossorigin="anonymous"></script>
</body>

</html>
