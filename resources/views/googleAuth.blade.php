<!DOCTYPE html>
<html>
    <style>
        /* Base button styles */
            .btn {
                display: inline-block;
                font-weight: 400;
                text-align: center;
                vertical-align: middle;
                cursor: pointer;
                border: 1px solid transparent;
                border-radius: 4px;
                padding: 0.375rem 0.75rem;
                font-size: 1rem;
                line-height: 1.5;
                transition: background-color 0.15s ease-in-out, border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
            }

            /* Large primary button styles */
            .btn-lg-primary {
                font-size: 1.25rem;
                padding: 0.5rem 1rem;
                border-radius: 0.375rem;
                background-color: #007bff; /* Primary color */
                color: #fff; /* Text color */
                border-color: #007bff; /* Border color */
            }

            .btn-lg-primary:hover {
                background-color: #0056b3; /* Darker primary color */
                border-color: #004085; /* Darker border color */
            }

            .btn-lg-primary:focus, .btn-lg-primary.focus {
                box-shadow: 0 0 0 0.2rem rgba(38, 143, 255, 0.5); /* Primary focus shadow */
            }

            /* Full-width button styles */
            .btn-block {
                display: block;
                width: 100%;
            }

            /* Optional: Adding some spacing between buttons */
            .btn + .btn {
                margin-left: 0.5rem;
                margin-top: 0.5rem;
            }
            /* General styles for row and column */
            .row {
                display: flex;
                padding:100px;
            }

            .col-md-12 {
                flex: 0 0 100%;
                max-width: 100%;
                padding-left: 500px;
                padding-right: 500px;

            }

            /* Custom styles for the row-block class */
            .row-block {
                background-color: #f8f9fa; /* Light gray background */
                border: 1px solid #dee2e6; /* Light gray border */
                border-radius: 0.375rem; /* Rounded corners */
                padding: 1rem; /* Padding inside the block */
                margin-bottom: 1rem; /* Margin at the bottom of the block */
            }


    </style>
<head>
    <title>Laravel Login with Google Account Example</title>
   
</head>


<body>

    <div class=”container”>
       <div class=”row”>
       <div class="col-md-12" >
            <a href="{{ route('google-auth') }}" class="btn btn-lg-primary btn-block">
            <strong>Login With Google</strong>
          </a> 
         </div>
       </div>
    </div>
</body>
</html>